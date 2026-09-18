<?php

namespace App\Jobs;

use App\Enums\EventType;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\Organization;
use App\Services\GeminiSummaryService;
use App\Services\MeetingService;
use App\Support\CurrentOrganization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Final step of the Meeting pipeline: summarizes the transcript into a
 * structured Minutes of Meeting via Gemini, then notifies the creator.
 */
class GenerateMeetingMinutesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $organizationId,
        public readonly int $meetingId,
    ) {}

    public function handle(
        CurrentOrganization $currentOrganization,
        GeminiSummaryService $gemini,
        MeetingService $meetings,
    ): void {
        $organization = Organization::find($this->organizationId);

        if (! $organization) {
            return;
        }

        $currentOrganization->runFor($organization, function () use ($organization, $gemini, $meetings): void {
            $meeting = Meeting::with(['creator', 'event'])->find($this->meetingId);

            if (! $meeting) {
                return;
            }

            $committeeMembers = $organization->committeeMembers()
                ->get(['name', 'position'])
                ->map(fn ($member) => ['name' => $member->name, 'position' => $member->position])
                ->all();

            $confirmedAttendees = $this->confirmedAttendeesFor($meeting);

            $minutes = $gemini->summarize((string) $meeting->transcript, $meeting->title, $committeeMembers, $confirmedAttendees);

            $meeting->update([
                'minutes' => $minutes,
                'minutes_ms' => $this->translateBestEffort($gemini, $minutes, $meeting),
                'status' => MeetingStatus::Ready,
            ]);

            $meetings->notifyReady($meeting);
        });
    }

    /**
     * Attendance now lives on the Meeting's linked Event (Committee Meeting
     * type only) rather than on the Meeting itself — a standalone meeting,
     * or one linked to a plain Event, simply has no confirmed attendees and
     * falls back to the committee-roster cross-check in the prompt instead.
     *
     * @return array<int, array{name: string, position: string}>
     */
    private function confirmedAttendeesFor(Meeting $meeting): array
    {
        $event = $meeting->event;

        if (! $event || $event->type !== EventType::CommitteeMeeting) {
            return [];
        }

        return $event->attendees()
            ->with('committeeMember')
            ->get()
            ->map(fn ($attendee) => ['name' => $attendee->displayName(), 'position' => (string) $attendee->displayPosition()])
            ->all();
    }

    /**
     * The Malay translation is a nice-to-have alongside the primary English
     * minutes, not a requirement — a translation failure shouldn't stop the
     * meeting from reaching Ready with its (already-generated) English
     * minutes.
     */
    private function translateBestEffort(GeminiSummaryService $gemini, string $minutes, Meeting $meeting): ?string
    {
        try {
            return $gemini->translate($minutes, 'Malay (Bahasa Malaysia)');
        } catch (Throwable $e) {
            Log::warning('Failed to translate meeting minutes to Malay: '.$e->getMessage(), ['meeting_id' => $meeting->id]);

            return null;
        }
    }

    public function failed(Throwable $e): void
    {
        $meeting = Meeting::find($this->meetingId);

        if ($meeting) {
            app(MeetingService::class)->markFailed($meeting, 'Failed to generate minutes.');
        }

        Log::error('Meeting minutes generation failed permanently: '.$e->getMessage(), [
            'organization_id' => $this->organizationId,
            'meeting_id' => $this->meetingId,
        ]);
    }
}
