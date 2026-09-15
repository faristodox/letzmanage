<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SyncEventToGoogleCalendarJob;
use App\Models\Event;
use App\Models\Organization;
use App\Models\OrganizationCalendarSetting;
use App\Models\User;
use App\Models\UserGoogleAccount;
use App\Services\GoogleCalendarService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SyncEventToGoogleCalendarJobTest extends TestCase
{
    use RefreshDatabase;

    private function runJob(int $organizationId, int $eventId, string $action): void
    {
        (new SyncEventToGoogleCalendarJob($organizationId, $eventId, $action))
            ->handle(app(CurrentOrganization::class), app(GoogleCalendarService::class));
    }

    private function eventIn(Organization $organization, array $attributes = []): Event
    {
        return app(CurrentOrganization::class)->runFor(
            $organization,
            fn () => Event::factory()->create($attributes)
        );
    }

    public function test_shared_mode_creates_an_all_day_event_when_no_start_time_is_set(): void
    {
        Http::fake(['https://www.googleapis.com/calendar/v3/*' => Http::response(['id' => 'gcal-event-1'])]);

        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create();
        $event = $this->eventIn($organization, ['start_date' => '2026-11-20']);

        $this->runJob($organization->id, $event->id, 'upsert');

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/events')
                && $request['start']['date'] === '2026-11-20'
                && $request['end']['date'] === '2026-11-21';
        });
        $this->assertSame('gcal-event-1', $event->fresh()->google_event_id);
    }

    public function test_shared_mode_creates_a_timed_event_when_start_time_is_set(): void
    {
        Http::fake(['https://www.googleapis.com/calendar/v3/*' => Http::response(['id' => 'gcal-event-2'])]);

        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create();
        $event = $this->eventIn($organization, [
            'start_date' => '2026-11-20',
            'start_time' => '19:00',
        ]);

        $this->runJob($organization->id, $event->id, 'upsert');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request['start']['dateTime'], '2026-11-20T19:00:00'));
    }

    public function test_individual_mode_with_a_connected_creator_creates_an_event(): void
    {
        Http::fake(['https://www.googleapis.com/calendar/v3/*' => Http::response(['id' => 'gcal-event-3'])]);

        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->individualMode()->create();

        $creator = User::factory()->create(['organization_id' => $organization->id]);
        UserGoogleAccount::factory()->for($creator)->connected()->create(['organization_id' => $organization->id]);

        $event = $this->eventIn($organization, ['start_date' => '2026-11-20', 'created_by' => $creator->id]);

        $this->runJob($organization->id, $event->id, 'upsert');

        Http::assertSent(fn ($request) => $request->method() === 'POST');
        $this->assertSame('gcal-event-3', $event->fresh()->google_event_id);
    }

    public function test_individual_mode_with_an_unconnected_creator_sends_nothing(): void
    {
        Http::fake();

        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->individualMode()->create();

        $creator = User::factory()->create(['organization_id' => $organization->id]);
        $event = $this->eventIn($organization, ['start_date' => '2026-11-20', 'created_by' => $creator->id]);

        $this->runJob($organization->id, $event->id, 'upsert');

        Http::assertNothingSent();
        $this->assertNull($event->fresh()->google_event_id);
    }

    public function test_disabled_mode_sends_nothing(): void
    {
        Http::fake();

        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->create();
        $event = $this->eventIn($organization, ['start_date' => '2026-11-20']);

        $this->runJob($organization->id, $event->id, 'upsert');

        Http::assertNothingSent();
    }

    public function test_no_calendar_setting_row_sends_nothing(): void
    {
        Http::fake();

        $organization = Organization::factory()->create();
        $event = $this->eventIn($organization, ['start_date' => '2026-11-20']);

        $this->runJob($organization->id, $event->id, 'upsert');

        Http::assertNothingSent();
    }

    public function test_delete_action_removes_the_event_and_clears_the_stored_id(): void
    {
        Http::fake(['https://www.googleapis.com/calendar/v3/*' => Http::response([], 204)]);

        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create();
        $event = $this->eventIn($organization, ['google_event_id' => 'gcal-event-to-delete']);

        $this->runJob($organization->id, $event->id, 'delete');

        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), 'gcal-event-to-delete'));
        $this->assertNull($event->fresh()->google_event_id);
    }

    public function test_a_server_error_throws_so_the_job_can_retry(): void
    {
        Http::fake(['https://www.googleapis.com/calendar/v3/*' => Http::response(['error' => 'boom'], 500)]);

        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create();
        $event = $this->eventIn($organization, ['start_date' => '2026-11-20']);

        $this->expectException(RuntimeException::class);

        $this->runJob($organization->id, $event->id, 'upsert');
    }
}
