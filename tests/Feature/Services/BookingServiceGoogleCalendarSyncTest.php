<?php

namespace Tests\Feature\Services;

use App\Enums\ApprovalMode;
use App\Enums\BookingStatus;
use App\Enums\RoleName;
use App\Jobs\SyncBookingToGoogleCalendarJob;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\OfficeSpace;
use App\Models\Organization;
use App\Models\User;
use App\Services\BookingService;
use App\Services\SystemSettingService;
use App\Support\CurrentOrganization;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BookingServiceGoogleCalendarSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Bookings only carry an organization_id once one is actively set via
     * CurrentOrganization (normally done by the SetCurrentOrganization
     * middleware on a real request) — mirrors the pattern used throughout
     * tests/Feature/Services/EventPaymentConfirmationServiceTest.php.
     */
    private function inOrganization(Organization $organization, callable $callback): mixed
    {
        return app(CurrentOrganization::class)->runFor($organization, $callback);
    }

    public function test_auto_approved_booking_queues_an_upsert_sync(): void
    {
        Queue::fake();

        $organization = Organization::factory()->create();

        $booking = $this->inOrganization($organization, function () {
            $branch = Branch::factory()->create();
            $space = OfficeSpace::factory()->create(['branch_id' => $branch->id]);
            app(SystemSettingService::class)->setApprovalMode(ApprovalMode::Auto, $branch->id);

            $staff = User::factory()->create(['branch_id' => $branch->id]);
            $staff->assignRole(RoleName::Staff->value);

            $date = now()->addDay()->format('Y-m-d');

            return app(BookingService::class)->create([
                'branch_id' => $branch->id,
                'user_id' => $staff->id,
                'space_id' => $space->id,
                'start_time' => "{$date} 09:00:00",
                'end_time' => "{$date} 10:00:00",
            ]);
        });

        Queue::assertPushed(SyncBookingToGoogleCalendarJob::class, fn ($job) => $job->organizationId === $organization->id
            && $job->bookingId === $booking->id && $job->action === 'upsert');
    }

    public function test_manually_approved_pending_booking_does_not_queue_a_sync_on_create(): void
    {
        Queue::fake();

        $organization = Organization::factory()->create();

        $this->inOrganization($organization, function () {
            $branch = Branch::factory()->create();
            $space = OfficeSpace::factory()->create(['branch_id' => $branch->id]);
            app(SystemSettingService::class)->setApprovalMode(ApprovalMode::Manual, $branch->id);

            $staff = User::factory()->create(['branch_id' => $branch->id]);
            $staff->assignRole(RoleName::Staff->value);

            $date = now()->addDay()->format('Y-m-d');

            app(BookingService::class)->create([
                'branch_id' => $branch->id,
                'user_id' => $staff->id,
                'space_id' => $space->id,
                'start_time' => "{$date} 09:00:00",
                'end_time' => "{$date} 10:00:00",
            ]);
        });

        Queue::assertNothingPushed();
    }

    public function test_auto_approved_guest_booking_queues_an_upsert_sync(): void
    {
        Queue::fake();

        $organization = Organization::factory()->create();

        $booking = $this->inOrganization($organization, function () {
            $branch = Branch::factory()->create();
            $space = OfficeSpace::factory()->create(['branch_id' => $branch->id]);
            app(SystemSettingService::class)->setApprovalMode(ApprovalMode::Auto, $branch->id);

            $date = now()->addDay()->format('Y-m-d');

            return app(BookingService::class)->createGuestBooking([
                'branch_id' => $branch->id,
                'space_id' => $space->id,
                'start_time' => "{$date} 09:00:00",
                'end_time' => "{$date} 10:00:00",
                'guest_name' => 'Jane Visitor',
                'guest_email' => 'jane@example.com',
            ]);
        });

        Queue::assertPushed(SyncBookingToGoogleCalendarJob::class, fn ($job) => $job->bookingId === $booking->id && $job->action === 'upsert');
    }

    public function test_approving_a_pending_booking_queues_an_upsert_sync(): void
    {
        $organization = Organization::factory()->create();

        [$booking, $manager] = $this->inOrganization($organization, function () {
            $branch = Branch::factory()->create();
            $space = OfficeSpace::factory()->create(['branch_id' => $branch->id]);

            $manager = User::factory()->create(['branch_id' => $branch->id]);
            $manager->assignRole(RoleName::Manager->value);

            $booking = Booking::factory()->create([
                'branch_id' => $branch->id,
                'space_id' => $space->id,
                'status' => BookingStatus::Pending,
            ]);

            return [$booking, $manager];
        });

        Queue::fake();

        $this->inOrganization($organization, fn () => app(BookingService::class)->approve($booking, $manager));

        Queue::assertPushed(SyncBookingToGoogleCalendarJob::class, fn ($job) => $job->bookingId === $booking->id && $job->action === 'upsert');
    }

    public function test_rejecting_a_never_synced_booking_does_not_queue_a_sync(): void
    {
        $organization = Organization::factory()->create();

        [$booking, $manager] = $this->inOrganization($organization, function () {
            $branch = Branch::factory()->create();
            $space = OfficeSpace::factory()->create(['branch_id' => $branch->id]);

            $manager = User::factory()->create(['branch_id' => $branch->id]);
            $manager->assignRole(RoleName::Manager->value);

            $booking = Booking::factory()->create([
                'branch_id' => $branch->id,
                'space_id' => $space->id,
                'status' => BookingStatus::Pending,
                'google_event_id' => null,
            ]);

            return [$booking, $manager];
        });

        Queue::fake();

        $this->inOrganization($organization, fn () => app(BookingService::class)->reject($booking, $manager, 'No longer needed'));

        Queue::assertNothingPushed();
    }

    public function test_rejecting_a_previously_synced_booking_queues_a_delete_sync(): void
    {
        $organization = Organization::factory()->create();

        [$booking, $manager] = $this->inOrganization($organization, function () {
            $branch = Branch::factory()->create();
            $space = OfficeSpace::factory()->create(['branch_id' => $branch->id]);

            $manager = User::factory()->create(['branch_id' => $branch->id]);
            $manager->assignRole(RoleName::Manager->value);

            $booking = Booking::factory()->create([
                'branch_id' => $branch->id,
                'space_id' => $space->id,
                'status' => BookingStatus::Approved,
                'google_event_id' => 'google-event-123',
            ]);

            return [$booking, $manager];
        });

        Queue::fake();

        $this->inOrganization($organization, fn () => app(BookingService::class)->reject($booking, $manager, 'Conflict resolved elsewhere'));

        Queue::assertPushed(SyncBookingToGoogleCalendarJob::class, fn ($job) => $job->bookingId === $booking->id && $job->action === 'delete');
    }

    public function test_cancelling_a_synced_booking_queues_a_delete_sync(): void
    {
        $organization = Organization::factory()->create();

        $booking = $this->inOrganization($organization, fn () => Booking::factory()->create([
            'status' => BookingStatus::Approved,
            'google_event_id' => 'google-event-456',
        ]));

        Queue::fake();

        $this->inOrganization($organization, fn () => app(BookingService::class)->cancel($booking));

        Queue::assertPushed(SyncBookingToGoogleCalendarJob::class, fn ($job) => $job->bookingId === $booking->id && $job->action === 'delete');
    }

    public function test_cancelling_an_unsynced_booking_does_not_queue_a_sync(): void
    {
        $organization = Organization::factory()->create();

        $booking = $this->inOrganization($organization, fn () => Booking::factory()->create([
            'status' => BookingStatus::Pending,
            'google_event_id' => null,
        ]));

        Queue::fake();

        $this->inOrganization($organization, fn () => app(BookingService::class)->cancel($booking));

        Queue::assertNothingPushed();
    }
}
