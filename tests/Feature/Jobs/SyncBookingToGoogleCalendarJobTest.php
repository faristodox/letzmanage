<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SyncBookingToGoogleCalendarJob;
use App\Models\Booking;
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

class SyncBookingToGoogleCalendarJobTest extends TestCase
{
    use RefreshDatabase;

    private function runJob(int $organizationId, int $bookingId, string $action): void
    {
        (new SyncBookingToGoogleCalendarJob($organizationId, $bookingId, $action))
            ->handle(app(CurrentOrganization::class), app(GoogleCalendarService::class));
    }

    private function bookingIn(Organization $organization, array $attributes = []): Booking
    {
        return app(CurrentOrganization::class)->runFor(
            $organization,
            fn () => Booking::factory()->create($attributes)
        );
    }

    public function test_shared_mode_creates_an_event_and_stores_its_id(): void
    {
        Http::fake(['https://www.googleapis.com/calendar/v3/*' => Http::response(['id' => 'gcal-event-1'])]);

        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create();
        $booking = $this->bookingIn($organization);

        $this->runJob($organization->id, $booking->id, 'upsert');

        Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/events'));
        $this->assertSame('gcal-event-1', $booking->fresh()->google_event_id);
    }

    public function test_individual_mode_with_a_connected_user_creates_an_event(): void
    {
        Http::fake(['https://www.googleapis.com/calendar/v3/*' => Http::response(['id' => 'gcal-event-2'])]);

        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->individualMode()->create();

        $user = User::factory()->create(['organization_id' => $organization->id]);
        UserGoogleAccount::factory()->for($user)->connected()->create(['organization_id' => $organization->id]);

        $booking = $this->bookingIn($organization, ['user_id' => $user->id]);

        $this->runJob($organization->id, $booking->id, 'upsert');

        Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/events'));
        $this->assertSame('gcal-event-2', $booking->fresh()->google_event_id);
    }

    public function test_individual_mode_with_an_unconnected_user_sends_nothing(): void
    {
        Http::fake();

        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->individualMode()->create();

        $user = User::factory()->create(['organization_id' => $organization->id]);
        $booking = $this->bookingIn($organization, ['user_id' => $user->id]);

        $this->runJob($organization->id, $booking->id, 'upsert');

        Http::assertNothingSent();
        $this->assertNull($booking->fresh()->google_event_id);
    }

    public function test_individual_mode_guest_booking_sends_nothing(): void
    {
        Http::fake();

        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->individualMode()->create();

        $booking = $this->bookingIn($organization, ['user_id' => null, 'guest_name' => 'Guest', 'guest_email' => 'g@example.com']);

        $this->runJob($organization->id, $booking->id, 'upsert');

        Http::assertNothingSent();
    }

    public function test_disabled_mode_sends_nothing(): void
    {
        Http::fake();

        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->create();
        $booking = $this->bookingIn($organization);

        $this->runJob($organization->id, $booking->id, 'upsert');

        Http::assertNothingSent();
    }

    public function test_no_calendar_setting_row_sends_nothing(): void
    {
        Http::fake();

        $organization = Organization::factory()->create();
        $booking = $this->bookingIn($organization);

        $this->runJob($organization->id, $booking->id, 'upsert');

        Http::assertNothingSent();
    }

    public function test_expired_access_token_is_refreshed_before_the_event_call(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'new-access-token', 'expires_in' => 3600]),
            'https://www.googleapis.com/calendar/v3/*' => Http::response(['id' => 'gcal-event-3']),
        ]);

        $organization = Organization::factory()->create();
        $setting = OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create([
            'google_token_expires_at' => now()->subMinute(),
        ]);
        $booking = $this->bookingIn($organization);

        $this->runJob($organization->id, $booking->id, 'upsert');

        $requests = Http::recorded();
        $this->assertCount(2, $requests);
        $this->assertStringContainsString('oauth2.googleapis.com/token', $requests[0][0]->url());
        $this->assertStringContainsString('/events', $requests[1][0]->url());

        $this->assertSame('new-access-token', $setting->fresh()->google_access_token);
    }

    public function test_delete_action_removes_the_event_and_clears_the_stored_id(): void
    {
        Http::fake(['https://www.googleapis.com/calendar/v3/*' => Http::response([], 204)]);

        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create();
        $booking = $this->bookingIn($organization, ['google_event_id' => 'gcal-event-to-delete']);

        $this->runJob($organization->id, $booking->id, 'delete');

        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), 'gcal-event-to-delete'));
        $this->assertNull($booking->fresh()->google_event_id);
    }

    public function test_delete_action_treats_a_404_as_already_gone(): void
    {
        Http::fake(['https://www.googleapis.com/calendar/v3/*' => Http::response([], 404)]);

        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create();
        $booking = $this->bookingIn($organization, ['google_event_id' => 'already-gone']);

        $this->runJob($organization->id, $booking->id, 'delete');

        $this->assertNull($booking->fresh()->google_event_id);
    }

    public function test_a_server_error_throws_so_the_job_can_retry(): void
    {
        Http::fake(['https://www.googleapis.com/calendar/v3/*' => Http::response(['error' => 'boom'], 500)]);

        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create();
        $booking = $this->bookingIn($organization);

        $this->expectException(RuntimeException::class);

        $this->runJob($organization->id, $booking->id, 'upsert');
    }
}
