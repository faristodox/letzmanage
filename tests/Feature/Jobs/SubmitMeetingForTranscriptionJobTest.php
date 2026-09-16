<?php

namespace Tests\Feature\Jobs;

use App\Enums\MeetingStatus;
use App\Jobs\PollMeetingTranscriptionJob;
use App\Jobs\SubmitMeetingForTranscriptionJob;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\OrganizationCalendarSetting;
use App\Models\User;
use App\Services\ArchiveFileService;
use App\Services\GoogleSpeechToTextService;
use App\Services\MeetingService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SubmitMeetingForTranscriptionJobTest extends TestCase
{
    use RefreshDatabase;

    private ?string $localAudioPath = null;

    protected function tearDown(): void
    {
        if ($this->localAudioPath && file_exists($this->localAudioPath)) {
            unlink($this->localAudioPath);
        }

        parent::tearDown();
    }

    private function fakeAudioFile(): string
    {
        $this->localAudioPath = tempnam(sys_get_temp_dir(), 'meeting').'.wav';
        file_put_contents($this->localAudioPath, 'fake-audio-bytes');

        return $this->localAudioPath;
    }

    private function runJob(int $organizationId, int $meetingId, string $localPath, string $mimeType): void
    {
        (new SubmitMeetingForTranscriptionJob($organizationId, $meetingId, $localPath, $mimeType))
            ->handle(
                app(CurrentOrganization::class),
                app(GoogleSpeechToTextService::class),
                app(ArchiveFileService::class),
                app(MeetingService::class),
            );
    }

    public function test_uploads_submits_and_archives_then_dispatches_the_poll_job(): void
    {
        Queue::fake();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
            'https://storage.googleapis.com/upload/storage/v1/*' => Http::response(['name' => 'meetings/1/x.wav']),
            'https://speech.googleapis.com/v1/speech:longrunningrecognize' => Http::response(['name' => 'op-123']),
            'https://www.googleapis.com/drive/v3/files/*/permissions' => Http::response(['id' => 'permission-1']),
            'https://www.googleapis.com/drive/v3/files' => Http::response(['id' => 'drive-file-1']),
            'https://www.googleapis.com/upload/drive/v3/files/*' => Http::response(['id' => 'drive-file-1']),
        ]);

        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->archiveEnabled()->create();
        $creator = User::factory()->create(['organization_id' => $organization->id]);
        $meeting = app(CurrentOrganization::class)->runFor(
            $organization,
            fn () => Meeting::factory()->for($organization)->pending()->create(['created_by' => $creator->id])
        );

        $path = $this->fakeAudioFile();

        $this->runJob($organization->id, $meeting->id, $path, 'audio/wav');

        $meeting->refresh();
        $this->assertSame(MeetingStatus::Transcribing, $meeting->status);
        $this->assertSame('op-123', $meeting->gcs_operation_name);
        $this->assertNotNull($meeting->audio_gcs_object);
        $this->assertNotNull($meeting->archived_file_id);
        $this->assertFileDoesNotExist($path);

        Queue::assertPushed(PollMeetingTranscriptionJob::class, fn ($job) => $job->meetingId === $meeting->id && $job->attempt === 1);
    }

    public function test_still_succeeds_when_archive_is_not_configured_for_the_org(): void
    {
        Queue::fake();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
            'https://storage.googleapis.com/upload/storage/v1/*' => Http::response(['name' => 'meetings/1/x.wav']),
            'https://speech.googleapis.com/v1/speech:longrunningrecognize' => Http::response(['name' => 'op-123']),
        ]);

        $organization = Organization::factory()->create();
        $creator = User::factory()->create(['organization_id' => $organization->id]);
        $meeting = app(CurrentOrganization::class)->runFor(
            $organization,
            fn () => Meeting::factory()->for($organization)->pending()->create(['created_by' => $creator->id])
        );

        $path = $this->fakeAudioFile();

        $this->runJob($organization->id, $meeting->id, $path, 'audio/wav');

        $meeting->refresh();
        $this->assertSame(MeetingStatus::Transcribing, $meeting->status);
        $this->assertNull($meeting->archived_file_id);
    }

    public function test_failed_marks_the_meeting_failed_and_removes_the_temp_file(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $organization = Organization::factory()->create();
        $creator = User::factory()->create(['organization_id' => $organization->id]);
        $meeting = app(CurrentOrganization::class)->runFor(
            $organization,
            fn () => Meeting::factory()->for($organization)->pending()->create(['created_by' => $creator->id])
        );

        $path = $this->fakeAudioFile();

        (new SubmitMeetingForTranscriptionJob($organization->id, $meeting->id, $path, 'audio/wav'))
            ->failed(new \RuntimeException('boom'));

        $meeting->refresh();
        $this->assertSame(MeetingStatus::Failed, $meeting->status);
        $this->assertNotNull($meeting->failure_reason);
        $this->assertFileDoesNotExist($path);
    }
}
