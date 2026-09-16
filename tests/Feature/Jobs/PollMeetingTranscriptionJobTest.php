<?php

namespace Tests\Feature\Jobs;

use App\Enums\MeetingStatus;
use App\Jobs\GenerateMeetingMinutesJob;
use App\Jobs\PollMeetingTranscriptionJob;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\User;
use App\Services\GoogleSpeechToTextService;
use App\Services\MeetingService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PollMeetingTranscriptionJobTest extends TestCase
{
    use RefreshDatabase;

    private function runJob(int $organizationId, int $meetingId, int $attempt = 1): void
    {
        (new PollMeetingTranscriptionJob($organizationId, $meetingId, $attempt))
            ->handle(
                app(CurrentOrganization::class),
                app(GoogleSpeechToTextService::class),
                app(MeetingService::class),
            );
    }

    private function transcribingMeeting(Organization $organization, User $creator): Meeting
    {
        return app(CurrentOrganization::class)->runFor(
            $organization,
            fn () => Meeting::factory()->for($organization)->transcribing()->create(['created_by' => $creator->id])
        );
    }

    private function fakeAuth(array $extra = []): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
            ...$extra,
        ]);
    }

    public function test_re_dispatches_itself_when_not_done_yet(): void
    {
        Queue::fake();
        $this->fakeAuth(['https://speech.googleapis.com/v1/operations/*' => Http::response(['done' => false])]);

        $organization = Organization::factory()->create();
        $creator = User::factory()->create(['organization_id' => $organization->id]);
        $meeting = $this->transcribingMeeting($organization, $creator);

        $this->runJob($organization->id, $meeting->id, 5);

        $meeting->refresh();
        $this->assertSame(MeetingStatus::Transcribing, $meeting->status);

        Queue::assertPushed(PollMeetingTranscriptionJob::class, fn ($job) => $job->meetingId === $meeting->id && $job->attempt === 6);
    }

    public function test_marks_failed_after_max_attempts(): void
    {
        Queue::fake();
        Notification::fake();
        $this->fakeAuth(['https://speech.googleapis.com/v1/operations/*' => Http::response(['done' => false])]);

        $organization = Organization::factory()->create();
        $creator = User::factory()->create(['organization_id' => $organization->id]);
        $meeting = $this->transcribingMeeting($organization, $creator);

        $this->runJob($organization->id, $meeting->id, 60);

        $meeting->refresh();
        $this->assertSame(MeetingStatus::Failed, $meeting->status);
        $this->assertStringContainsString('timed out', $meeting->failure_reason);
        Queue::assertNotPushed(PollMeetingTranscriptionJob::class);
    }

    public function test_marks_failed_when_google_reports_an_error(): void
    {
        Notification::fake();
        $this->fakeAuth(['https://speech.googleapis.com/v1/operations/*' => Http::response([
            'done' => true,
            'error' => ['message' => 'audio too long'],
        ])]);

        $organization = Organization::factory()->create();
        $creator = User::factory()->create(['organization_id' => $organization->id]);
        $meeting = $this->transcribingMeeting($organization, $creator);

        $this->runJob($organization->id, $meeting->id);

        $meeting->refresh();
        $this->assertSame(MeetingStatus::Failed, $meeting->status);
        $this->assertStringContainsString('audio too long', $meeting->failure_reason);
    }

    public function test_saves_transcript_and_dispatches_summarization_when_done(): void
    {
        Queue::fake();
        $this->fakeAuth([
            'https://speech.googleapis.com/v1/operations/*' => Http::response([
                'done' => true,
                'response' => ['results' => [['alternatives' => [['transcript' => 'Hello team.']]]]],
            ]),
            'https://storage.googleapis.com/storage/v1/b/*/o/*' => Http::response([], 200),
        ]);

        $organization = Organization::factory()->create();
        $creator = User::factory()->create(['organization_id' => $organization->id]);
        $meeting = app(CurrentOrganization::class)->runFor(
            $organization,
            fn () => Meeting::factory()->for($organization)->transcribing()->create([
                'created_by' => $creator->id,
                'audio_gcs_object' => 'meetings/1/test.wav',
            ])
        );

        $this->runJob($organization->id, $meeting->id);

        $meeting->refresh();
        $this->assertSame(MeetingStatus::Summarizing, $meeting->status);
        $this->assertSame('Hello team.', $meeting->transcript);

        Queue::assertPushed(GenerateMeetingMinutesJob::class, fn ($job) => $job->meetingId === $meeting->id);
    }

    public function test_ignores_a_stale_job_for_a_meeting_that_already_moved_on(): void
    {
        Queue::fake();
        $this->fakeAuth();

        $organization = Organization::factory()->create();
        $creator = User::factory()->create(['organization_id' => $organization->id]);
        $meeting = app(CurrentOrganization::class)->runFor(
            $organization,
            fn () => Meeting::factory()->for($organization)->create(['created_by' => $creator->id, 'status' => MeetingStatus::Ready])
        );

        $this->runJob($organization->id, $meeting->id);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'operations'));
        Queue::assertNothingPushed();
    }
}
