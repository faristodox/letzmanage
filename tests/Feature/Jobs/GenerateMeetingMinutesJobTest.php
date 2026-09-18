<?php

namespace Tests\Feature\Jobs;

use App\Enums\EventType;
use App\Enums\MeetingStatus;
use App\Jobs\GenerateMeetingMinutesJob;
use App\Models\CommitteeMember;
use App\Models\Event;
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

    private function summarizingMeeting(Organization $organization, User $creator, ?int $eventId = null): Meeting
    {
        return app(CurrentOrganization::class)->runFor(
            $organization,
            fn () => Meeting::factory()->for($organization)->create([
                'created_by' => $creator->id,
                'event_id' => $eventId,
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

    public function test_confirmed_checkin_attendees_take_priority_over_the_roster(): void
    {
        Notification::fake();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Title: Usrah Session']]]]],
            ]),
        ]);

        $organization = Organization::factory()->create();
        $creator = User::factory()->create(['organization_id' => $organization->id]);
        CommitteeMember::factory()->for($organization)->create(['name' => 'Someone Else', 'position' => 'Treasurer']);
        $checkedIn = CommitteeMember::factory()->for($organization)->create(['name' => 'Ahmad Zaki', 'position' => 'President']);
        $event = Event::factory()->for($organization)->create(['type' => EventType::CommitteeMeeting]);
        $meeting = $this->summarizingMeeting($organization, $creator, $event->id);
        $event->attendees()->create(['committee_member_id' => $checkedIn->id, 'checked_in_at' => now()]);

        $this->runJob($organization->id, $meeting->id);

        Http::assertSent(function ($request) {
            $prompt = $request['contents'][0]['parts'][0]['text'];

            return str_contains($prompt, 'confirmed via check-in')
                && str_contains($prompt, 'Ahmad Zaki (President)')
                && ! str_contains($prompt, 'Known committee/board members');
        });
    }

    public function test_guest_attendees_are_included_in_the_confirmed_attendees_list(): void
    {
        Notification::fake();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Title: Usrah Session']]]]],
            ]),
        ]);

        $organization = Organization::factory()->create();
        $creator = User::factory()->create(['organization_id' => $organization->id]);
        $event = Event::factory()->for($organization)->create(['type' => EventType::CommitteeMeeting]);
        $meeting = $this->summarizingMeeting($organization, $creator, $event->id);
        $event->attendees()->create(['guest_name' => 'Guest Speaker', 'guest_position' => null, 'checked_in_at' => now()]);

        $this->runJob($organization->id, $meeting->id);

        Http::assertSent(function ($request) {
            $prompt = $request['contents'][0]['parts'][0]['text'];

            return str_contains($prompt, 'confirmed via check-in') && str_contains($prompt, 'Guest Speaker');
        });
    }

    public function test_saves_structured_agenda_items_alongside_the_minutes(): void
    {
        Notification::fake();
        Http::fake(function ($request) {
            $prompt = $request['contents'][0]['parts'][0]['text'];

            if (str_contains($prompt, 'ONLY a single valid JSON object')) {
                return Http::response([
                    'candidates' => [['content' => ['parts' => [['text' => json_encode([
                        'attendees' => [],
                        'agenda_items' => [['topic' => 'Ta\'aruf', 'sub_points' => ['Sesi perkenalan'], 'action_by' => 'Makluman', 'notes' => '']],
                    ])]]]]],
                ]);
            }

            if (str_contains($prompt, 'Translate every text value')) {
                return Http::response([
                    'candidates' => [['content' => ['parts' => [['text' => json_encode([
                        'attendees' => [],
                        'agenda_items' => [['topic' => "Ta'aruf", 'sub_points' => ['Sesi perkenalan bahasa Melayu'], 'action_by' => 'Makluman', 'notes' => '']],
                    ])]]]]],
                ]);
            }

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
        $this->assertSame("Ta'aruf", $meeting->agenda_items['agenda_items'][0]['topic']);
        $this->assertSame('Sesi perkenalan bahasa Melayu', $meeting->agenda_items_ms['agenda_items'][0]['sub_points'][0]);
    }

    public function test_agenda_extraction_failure_does_not_prevent_the_meeting_from_reaching_ready(): void
    {
        Notification::fake();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Title: Usrah Session']]]]],
            ]),
        ]);

        $organization = Organization::factory()->create();
        $creator = User::factory()->create(['organization_id' => $organization->id]);
        $meeting = $this->summarizingMeeting($organization, $creator);

        $this->runJob($organization->id, $meeting->id);

        $meeting->refresh();
        $this->assertSame(MeetingStatus::Ready, $meeting->status);
        $this->assertSame('Title: Usrah Session', $meeting->minutes);
        $this->assertNull($meeting->agenda_items);
        $this->assertNull($meeting->agenda_items_ms);
        Notification::assertSentTo($creator, MeetingMinutesReadyNotification::class);
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
