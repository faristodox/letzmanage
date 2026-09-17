<?php

namespace App\Jobs;

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

        $currentOrganization->runFor($organization, function () use ($gemini, $meetings): void {
            $meeting = Meeting::with('creator')->find($this->meetingId);

            if (! $meeting) {
                return;
            }

            $minutes = $gemini->summarize((string) $meeting->transcript, $meeting->title);

            $meeting->update([
                'minutes' => $minutes,
                'minutes_ms' => $this->translateBestEffort($gemini, $minutes, $meeting),
                'status' => MeetingStatus::Ready,
            ]);

            $meetings->notifyReady($meeting);
        });
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
