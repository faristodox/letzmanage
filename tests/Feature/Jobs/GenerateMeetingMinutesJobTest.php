<?php

namespace Tests\Feature\Jobs;

use App\Enums\MeetingStatus;
use App\Jobs\GenerateMeetingMinutesJob;
use App\Models\CommitteeMember;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\MeetingFailedNotification;
use App\Notifications\MeetingMinutesReadyNotification;
use App\Services\GeminiSummaryService;
use App\Services\MeetingService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class GenerateMeetingMinutesJobTest extends TestCase
{
    use RefreshDatabase;

    private function runJob(int $organizationId, int $meetingId): void
    {
        (new GenerateMeetingMinutesJob($organizationId, $meetingId))
            ->handle(
                app(CurrentOrganization::class),
                app(GeminiSummaryService::class),
                app(MeetingService::class),
            );
    }

    private function summarizingMeeting(Organization $organization, User $creator): Meeting
    {
        return app(CurrentOrganization::class)->runFor(
            $organization,
            fn () => Meeting::factory()->for($organization)->create([
                'created_by' => $creator->id,
                'status' => MeetingStatus::Summarizing,
                'transcript' => 'Faris: hello. Ahmad: hi.',
                'minutes' => null,
            ])
        );
    }

    public function test_saves_minutes_and_notifies_the_creator(): void
    {
        Notification::fake();
        Http::fake(function ($request) {
            $prompt = $request['contents'][0]['parts'][0]['text'];

            if (str_contains($prompt, 'Translate the following Minutes of Meeting')) {
                return Http::response([
                    'candidates' => [['content' => ['parts' => [['text' => 'Tajuk: Sesi Usrah']]]]],
                ]);
            }

            return Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Title: Usrah Session']]]]],
            ]);
        });

        $organization = Organization::factory()->create();
        $creator = User::factory()->create(['organization_id' => $organization->id]);
        $meeting = $this->summarizingMeeting($organization, $creator);

        $this->runJob($organization->id, $meeting->id);

        $meeting->refresh();
        $this->assertSame(MeetingStatus::Ready, $meeting->status);
        $this->assertSame('Title: Usrah Session', $meeting->minutes);
        $this->assertSame('Tajuk: Sesi Usrah', $meeting->minutes_ms);
        Notification::assertSentTo($creator, MeetingMinutesReadyNotification::class);
    }

    public function test_translation_failure_does_not_prevent_the_meeting_from_reaching_ready(): void
    {
        Notification::fake();
        Http::fake(function ($request) {
            $prompt = $request['contents'][0]['parts'][0]['text'];

            if (str_contains($prompt, 'Translate the following Minutes of Meeting')) {
                return Http::response(['error' => 'bad request'], 400);
            }

            return Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Title: Usrah Session']]]]],
            ]);
        });

        $organization = Organization::factory()->create();
        $creator = User::factory()->create(['organization_id' => $organization->id]);
        $meeting = $this->summarizingMeeting($organization, $creator);

        $this->runJob($organization->id, $meeting->id);

        $meeting->refresh();
        $this->assertSame(MeetingStatus::Ready, $meeting->status);
        $this->assertSame('Title: Usrah Session', $meeting->minutes);
        $this->assertNull($meeting->minutes_ms);
        Notification::assertSentTo($creator, MeetingMinutesReadyNotification::class);
    }

    public function test_includes_the_organizations_committee_roster_in_the_summarize_prompt(): void
    {
        Notification::fake();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Title: Usrah Session']]]]],
            ]),
        ]);

        $organization = Organization::factory()->create();
        $creator = User::factory()->create(['organization_id' => $organization->id]);
        CommitteeMember::factory()->for($organization)->create(['name' => 'Ahmad Zaki', 'position' => 'President']);
        $meeting = $this->summarizingMeeting($organization, $creator);

        $this->runJob($organization->id, $meeting->id);

        Http::assertSent(function ($request) {
            $prompt = $request['contents'][0]['parts'][0]['text'];

            return str_contains($prompt, 'Ahmad Zaki (President)');
        });
    }

    public function test_failed_marks_the_meeting_failed_and_notifies(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create();
        $creator = User::factory()->create(['organization_id' => $organization->id]);
        $meeting = $this->summarizingMeeting($organization, $creator);

        (new GenerateMeetingMinutesJob($organization->id, $meeting->id))->failed(new RuntimeException('boom'));

        $meeting->refresh();
        $this->assertSame(MeetingStatus::Failed, $meeting->status);
        Notification::assertSentTo($creator, MeetingFailedNotification::class);
    }
}
