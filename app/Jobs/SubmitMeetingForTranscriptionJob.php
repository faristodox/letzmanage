<?php

namespace App\Jobs;

use App\Enums\MeetingStatus;
use App\Exceptions\ArchiveNotConfiguredException;
use App\Models\Meeting;
use App\Models\Organization;
use App\Services\ArchiveFileService;
use App\Services\GoogleSpeechToTextService;
use App\Services\MeetingService;
use App\Support\CurrentOrganization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * First step of the Meeting transcription pipeline: uploads the locally
 * staged audio to GCS, submits it for recognition, and — best-effort, never
 * blocking the transcription itself — archives a permanent copy to Google
 * Drive via the existing Archive feature if that org has it configured.
 * Dispatches PollMeetingTranscriptionJob to wait for the result.
 */
class SubmitMeetingForTranscriptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [30, 60, 120];

    private const ENCODINGS = [
        'audio/webm' => 'WEBM_OPUS',
        'audio/ogg' => 'OGG_OPUS',
        'audio/wav' => 'LINEAR16',
        'audio/x-wav' => 'LINEAR16',
        'audio/wave' => 'LINEAR16',
        'audio/mpeg' => 'MP3',
        'audio/mp3' => 'MP3',
        'audio/flac' => 'FLAC',
        'audio/x-flac' => 'FLAC',
    ];

    public function __construct(
        public readonly int $organizationId,
        public readonly int $meetingId,
        public readonly string $localAudioPath,
        public readonly string $mimeType,
    ) {}

    public function handle(
        CurrentOrganization $currentOrganization,
        GoogleSpeechToTextService $speech,
        ArchiveFileService $archive,
        MeetingService $meetings,
    ): void {
        $organization = Organization::find($this->organizationId);

        if (! $organization) {
            return;
        }

        $currentOrganization->runFor($organization, function () use ($organization, $speech, $archive, $meetings): void {
            $meeting = Meeting::with('creator')->find($this->meetingId);

            if (! $meeting) {
                return;
            }

            $meeting->update(['status' => MeetingStatus::Uploading]);

            $objectName = "meetings/{$this->organizationId}/{$meeting->id}-".Str::uuid().$this->extension();
            $encoding = self::ENCODINGS[$this->mimeType] ?? 'WEBM_OPUS';

            $speech->uploadAudio($this->localAudioPath, $objectName, $this->mimeType);

            $operationName = $speech->submitLongRunningRecognize(
                "gs://".config('services.google_speech.bucket')."/{$objectName}",
                $encoding,
                config('services.google_speech.language_code'),
            );

            $meeting->update([
                'audio_gcs_object' => $objectName,
                'gcs_operation_name' => $operationName,
                'status' => MeetingStatus::Transcribing,
            ]);

            $this->archiveBestEffort($archive, $organization, $meeting);

            $this->deleteLocalFile();

            PollMeetingTranscriptionJob::dispatch($this->organizationId, $meeting->id)->delay(now()->addMinutes(2));
        });
    }

    /**
     * Never lets an Archive failure (not configured, Drive error, etc.)
     * block the transcription pipeline — it's a nice-to-have, not a
     * requirement.
     */
    private function archiveBestEffort(ArchiveFileService $archive, Organization $organization, Meeting $meeting): void
    {
        if (! $meeting->creator) {
            return;
        }

        try {
            $friendlyName = Str::slug($meeting->title).$this->extension();
            $fakeUpload = new UploadedFile($this->localAudioPath, $friendlyName, $this->mimeType, null, true);
            $archivedFile = $archive->upload($organization, $meeting->creator, $fakeUpload);
            $meeting->update(['archived_file_id' => $archivedFile->id]);
        } catch (ArchiveNotConfiguredException $e) {
            // Fine — this org just doesn't have Archive set up.
        } catch (Throwable $e) {
            Log::warning('Failed to archive meeting recording: '.$e->getMessage(), ['meeting_id' => $meeting->id]);
        }
    }

    private function extension(): string
    {
        $extension = pathinfo($this->localAudioPath, PATHINFO_EXTENSION);

        return $extension ? ".{$extension}" : '';
    }

    private function deleteLocalFile(): void
    {
        if (file_exists($this->localAudioPath)) {
            @unlink($this->localAudioPath);
        }
    }

    public function failed(Throwable $e): void
    {
        $meeting = Meeting::find($this->meetingId);

        if ($meeting) {
            app(MeetingService::class)->markFailed($meeting, 'Upload/transcription submission failed.');
        }

        $this->deleteLocalFile();

        Log::error('Meeting transcription submission failed permanently: '.$e->getMessage(), [
            'organization_id' => $this->organizationId,
            'meeting_id' => $this->meetingId,
        ]);
    }
}
