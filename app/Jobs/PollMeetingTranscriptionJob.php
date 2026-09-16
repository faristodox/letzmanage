<?php

namespace App\Jobs;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\Organization;
use App\Services\GoogleSpeechToTextService;
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
 * Self-rescheduling poll of a Speech-to-Text long-running operation — this
 * app has no periodic scheduler, so "check again in a bit" is done by
 * dispatching a delayed copy of itself rather than adding one. Capped at
 * MAX_ATTEMPTS so a stuck operation eventually surfaces as Failed instead
 * of polling forever.
 */
class PollMeetingTranscriptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [30, 60, 120];

    private const MAX_ATTEMPTS = 60;

    public function __construct(
        public readonly int $organizationId,
        public readonly int $meetingId,
        public readonly int $attempt = 1,
    ) {}

    public function handle(
        CurrentOrganization $currentOrganization,
        GoogleSpeechToTextService $speech,
        MeetingService $meetings,
    ): void {
        $organization = Organization::find($this->organizationId);

        if (! $organization) {
            return;
        }

        $currentOrganization->runFor($organization, function () use ($speech, $meetings): void {
            $meeting = Meeting::find($this->meetingId);

            if (! $meeting) {
                return;
            }

            // Protects against a straggling delayed job firing after the
            // meeting already moved on (e.g. was marked Failed by something
            // else in the meantime).
            if ($meeting->status !== MeetingStatus::Transcribing) {
                return;
            }

            $status = $speech->checkOperationStatus($meeting->gcs_operation_name);

            if (! $status['done']) {
                $this->handleNotDone($meeting, $meetings);

                return;
            }

            if ($status['error']) {
                $meetings->markFailed($meeting, 'Transcription failed: '.($status['error']['message'] ?? 'unknown error'));

                return;
            }

            $transcript = $speech->extractTranscript($status['response'] ?? []);

            if ($meeting->audio_gcs_object) {
                $speech->deleteObject($meeting->audio_gcs_object);
            }

            $meeting->update(['transcript' => $transcript, 'status' => MeetingStatus::Summarizing]);

            GenerateMeetingMinutesJob::dispatch($this->organizationId, $meeting->id);
        });
    }

    private function handleNotDone(Meeting $meeting, MeetingService $meetings): void
    {
        if ($this->attempt >= self::MAX_ATTEMPTS) {
            $meetings->markFailed($meeting, 'Transcription timed out after 2 hours.');

            return;
        }

        self::dispatch($this->organizationId, $this->meetingId, $this->attempt + 1)->delay(now()->addMinutes(2));
    }

    public function failed(Throwable $e): void
    {
        $meeting = Meeting::find($this->meetingId);

        if ($meeting) {
            app(MeetingService::class)->markFailed($meeting, 'Failed while checking transcription status.');
        }

        Log::error('Meeting transcription polling failed permanently: '.$e->getMessage(), [
            'organization_id' => $this->organizationId,
            'meeting_id' => $this->meetingId,
            'attempt' => $this->attempt,
        ]);
    }
}
