<?php

namespace Tests\Feature\Services;

use App\Enums\MeetingStatus;
use App\Jobs\SubmitMeetingForTranscriptionJob;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\MeetingFailedNotification;
use App\Notifications\MeetingMinutesReadyNotification;
use App\Services\MeetingService;
use App\Services\SystemSettingService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MeetingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_from_upload_creates_a_pending_meeting_and_dispatches_the_submit_job(): void
    {
        Queue::fake();

        $organization = Organization::factory()->create();
        $creator = User::factory()->create(['organization_id' => $organization->id]);
        $file = UploadedFile::fake()->create('recording.wav', 500, 'audio/wav');

        app(CurrentOrganization::class)->set($organization);

        $meeting = app(MeetingService::class)->createFromUpload($organization, $creator, $file, null, 'Usrah Session', 3600);

        $this->assertSame(MeetingStatus::Pending, $meeting->status);
        $this->assertSame('Usrah Session', $meeting->title);
        $this->assertSame(3600, $meeting->duration_seconds);
        $this->assertNull($meeting->event_id);

        Queue::assertPushed(SubmitMeetingForTranscriptionJob::class, function ($job) use ($organization, $meeting) {
            return $job->organizationId === $organization->id
                && $job->meetingId === $meeting->id
                && $job->mimeType === 'audio/wav'
                && file_exists($job->localAudioPath);
        });
    }

    public function test_create_from_upload_can_link_to_an_event(): void
    {
        Queue::fake();

        $organization = Organization::factory()->create();
        $creator = User::factory()->create(['organization_id' => $organization->id]);
        $event = \App\Models\Event::factory()->for($organization)->create();
        $file = UploadedFile::fake()->create('recording.wav', 500, 'audio/wav');

        app(CurrentOrganization::class)->set($organization);

        $meeting = app(MeetingService::class)->createFromUpload($organization, $creator, $file, $event->id, 'Usrah Session');

        $this->assertSame($event->id, $meeting->event_id);
    }

    public function test_notify_ready_sends_when_email_notifications_are_enabled(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create();
        $creator = User::factory()->create(['organization_id' => $organization->id]);
        $meeting = Meeting::factory()->for($organization)->create(['created_by' => $creator->id]);

        app(MeetingService::class)->notifyReady($meeting);

        Notification::assertSentTo($creator, MeetingMinutesReadyNotification::class);
    }

    public function test_notify_ready_does_nothing_when_email_notifications_are_disabled(): void
    {
        Notification::fake();
        app(SystemSettingService::class)->setEmailNotificationsEnabled(false);

        $organization = Organization::factory()->create();
        $creator = User::factory()->create(['organization_id' => $organization->id]);
        $meeting = Meeting::factory()->for($organization)->create(['created_by' => $creator->id]);

        app(MeetingService::class)->notifyReady($meeting);

        Notification::assertNothingSent();
    }

    public function test_mark_failed_updates_status_and_notifies(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create();
        $creator = User::factory()->create(['organization_id' => $organization->id]);
        $meeting = Meeting::factory()->transcribing()->for($organization)->create(['created_by' => $creator->id]);

        app(MeetingService::class)->markFailed($meeting, 'Something went wrong.');

        $meeting->refresh();
        $this->assertSame(MeetingStatus::Failed, $meeting->status);
        $this->assertSame('Something went wrong.', $meeting->failure_reason);
        Notification::assertSentTo($creator, MeetingFailedNotification::class);
    }
}
