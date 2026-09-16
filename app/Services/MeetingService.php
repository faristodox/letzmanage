<?php

namespace App\Services;

use App\Enums\MeetingStatus;
use App\Jobs\SubmitMeetingForTranscriptionJob;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\MeetingFailedNotification;
use App\Notifications\MeetingMinutesReadyNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Keeps App\Livewire\Meetings\Index thin — stores the uploaded/recorded
 * audio locally, creates the Meeting record, and kicks off the async
 * transcription pipeline (App\Jobs\SubmitMeetingForTranscriptionJob and
 * what it dispatches after it).
 */
class MeetingService
{
    public function __construct(private readonly SystemSettingService $settings) {}

    public function createFromUpload(
        Organization $organization,
        User $creator,
        UploadedFile $file,
        ?int $eventId,
        string $title,
        ?int $recordedDurationSeconds = null,
    ): Meeting {
        $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'webm';
        $localPath = $file->storeAs('meetings/pending', Str::uuid().'.'.$extension, 'local');

        $meeting = Meeting::create([
            'organization_id' => $organization->id,
            'event_id' => $eventId,
            'created_by' => $creator->id,
            'title' => $title,
            'status' => MeetingStatus::Pending,
            'duration_seconds' => $recordedDurationSeconds,
        ]);

        try {
            SubmitMeetingForTranscriptionJob::dispatch(
                $organization->id,
                $meeting->id,
                Storage::disk('local')->path($localPath),
                $file->getMimeType() ?: 'application/octet-stream',
            );
        } catch (Throwable $e) {
            Log::warning('Failed to queue meeting transcription: '.$e->getMessage(), ['meeting_id' => $meeting->id]);
        }

        return $meeting;
    }

    public function markFailed(Meeting $meeting, string $reason): void
    {
        $meeting->update([
            'status' => MeetingStatus::Failed,
            'failure_reason' => $reason,
        ]);

        $this->notifyFailed($meeting);
    }

    public function notifyReady(Meeting $meeting): void
    {
        if (! $meeting->creator) {
            return;
        }

        $this->notifyEmail($meeting->creator, new MeetingMinutesReadyNotification($meeting));
    }

    public function notifyFailed(Meeting $meeting): void
    {
        if (! $meeting->creator) {
            return;
        }

        $this->notifyEmail($meeting->creator, new MeetingFailedNotification($meeting));
    }

    private function notifyEmail(mixed $notifiable, \Illuminate\Notifications\Notification $notification): void
    {
        if (! $this->settings->getEmailNotificationsEnabled()) {
            return;
        }

        try {
            Notification::send($notifiable, $notification);
        } catch (Throwable $e) {
            Log::warning('Meeting notification failed: '.$e->getMessage(), [
                'notification' => get_class($notification),
            ]);
        }
    }
}
