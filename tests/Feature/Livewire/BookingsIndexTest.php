<?php

namespace Tests\Feature\Livewire;

use App\Enums\ApprovalMode;
use App\Enums\BookingStatus;
use App\Enums\OfficeSpaceStatus;
use App\Enums\RoleName;
use App\Livewire\Bookings\Index;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\OfficeSpace;
use App\Models\User;
use App\Services\SystemSettingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingsIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_staff_booking_is_pending_under_manual_approval_and_manager_can_approve(): void
    {
        $branch = Branch::factory()->create();
        $space = OfficeSpace::factory()->create(['branch_id' => $branch->id, 'status' => OfficeSpaceStatus::Active]);

        app(SystemSettingService::class)->setApprovalMode(ApprovalMode::Manual, $branch->id);

        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $staff->assignRole(RoleName::Staff->value);

        $manager = User::factory()->create(['branch_id' => $branch->id]);
        $manager->assignRole(RoleName::Manager->value);

        Livewire::actingAs($staff)
            ->test(Index::class)
            ->call('create')
            ->set('space_id', $space->id)
            ->set('title', 'Team sync')
            ->set('date', now()->addDay()->format('Y-m-d'))
            ->set('start_time', '09:00')
            ->set('end_time', '10:00')
            ->call('save')
            ->assertHasNoErrors();

        $booking = Booking::where('title', 'Team sync')->first();
        $this->assertNotNull($booking);
        $this->assertSame(BookingStatus::Pending, $booking->status);

        // Staff cannot approve their own booking.
        Livewire::actingAs($staff)
            ->test(Index::class)
            ->call('confirmApprove', $booking->id)
            ->assertForbidden();

        Livewire::actingAs($manager)
            ->test(Index::class)
            ->call('confirmApprove', $booking->id)
            ->set('approveNote', 'Welcome - the front desk will let you in.')
            ->call('approve');

        $booking->refresh();
        $this->assertSame(BookingStatus::Approved, $booking->status);
        $this->assertSame('Welcome - the front desk will let you in.', $booking->notes);

        $rendered = (new \App\Notifications\BookingApprovedNotification($booking))->toMail($staff)->render();
        $this->assertStringContainsString('Welcome - the front desk will let you in.', $rendered);
    }

    public function test_approval_email_includes_branch_additional_info(): void
    {
        $branch = Branch::factory()->create();
        $space = OfficeSpace::factory()->create(['branch_id' => $branch->id, 'status' => OfficeSpaceStatus::Active]);

        app(SystemSettingService::class)->setApprovalEmailNote('Parking is available at the back entrance.', $branch->id);

        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $staff->assignRole(RoleName::Staff->value);

        $booking = Booking::factory()->create([
            'branch_id' => $branch->id,
            'space_id' => $space->id,
            'user_id' => $staff->id,
            'status' => BookingStatus::Approved,
        ]);

        $rendered = (new \App\Notifications\BookingApprovedNotification($booking))->toMail($staff)->render();
        $this->assertStringContainsString('Parking is available at the back entrance.', $rendered);
    }

    public function test_booking_is_auto_approved_under_auto_mode(): void
    {
        $branch = Branch::factory()->create();
        $space = OfficeSpace::factory()->create(['branch_id' => $branch->id, 'status' => OfficeSpaceStatus::Active]);

        app(SystemSettingService::class)->setApprovalMode(ApprovalMode::Auto, $branch->id);

        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $staff->assignRole(RoleName::Staff->value);

        Livewire::actingAs($staff)
            ->test(Index::class)
            ->call('create')
            ->set('space_id', $space->id)
            ->set('date', now()->addDay()->format('Y-m-d'))
            ->set('start_time', '09:00')
            ->set('end_time', '10:00')
            ->call('save')
            ->assertHasNoErrors();

        $booking = Booking::where('space_id', $space->id)->first();
        $this->assertSame(BookingStatus::Approved, $booking->status);
    }

    public function test_overlapping_booking_is_rejected_with_conflict_error(): void
    {
        $branch = Branch::factory()->create();
        $space = OfficeSpace::factory()->create(['branch_id' => $branch->id, 'status' => OfficeSpaceStatus::Active]);

        app(SystemSettingService::class)->setApprovalMode(ApprovalMode::Auto, $branch->id);

        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $staff->assignRole(RoleName::Staff->value);

        $date = now()->addDay()->format('Y-m-d');

        Livewire::actingAs($staff)
            ->test(Index::class)
            ->call('create')
            ->set('space_id', $space->id)
            ->set('date', $date)
            ->set('start_time', '09:00')
            ->set('end_time', '10:00')
            ->call('save')
            ->assertHasNoErrors();

        $component = Livewire::actingAs($staff)
            ->test(Index::class)
            ->call('create')
            ->set('space_id', $space->id)
            ->set('date', $date)
            ->set('start_time', '09:30')
            ->set('end_time', '10:30')
            ->call('save');

        $this->assertNotNull($component->get('errorMessage'));
        $this->assertSame(1, Booking::where('space_id', $space->id)->count());
    }

    public function test_staff_can_cancel_their_own_booking_but_not_others(): void
    {
        $branch = Branch::factory()->create();
        $space = OfficeSpace::factory()->create(['branch_id' => $branch->id]);

        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $staff->assignRole(RoleName::Staff->value);

        $otherStaff = User::factory()->create(['branch_id' => $branch->id]);
        $otherStaff->assignRole(RoleName::Staff->value);

        $ownBooking = Booking::factory()->create([
            'branch_id' => $branch->id,
            'space_id' => $space->id,
            'user_id' => $staff->id,
            'status' => BookingStatus::Pending,
        ]);

        $othersBooking = Booking::factory()->create([
            'branch_id' => $branch->id,
            'space_id' => $space->id,
            'user_id' => $otherStaff->id,
            'status' => BookingStatus::Pending,
        ]);

        Livewire::actingAs($staff)
            ->test(Index::class)
            ->call('confirmCancel', $othersBooking->id)
            ->assertForbidden();

        Livewire::actingAs($staff)
            ->test(Index::class)
            ->call('confirmCancel', $ownBooking->id)
            ->call('cancel');

        $this->assertSame(BookingStatus::Cancelled, $ownBooking->refresh()->status);
    }

    public function test_manager_can_reject_a_pending_booking_with_reason(): void
    {
        $branch = Branch::factory()->create();
        $space = OfficeSpace::factory()->create(['branch_id' => $branch->id]);

        $manager = User::factory()->create(['branch_id' => $branch->id]);
        $manager->assignRole(RoleName::Manager->value);

        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $staff->assignRole(RoleName::Staff->value);

        $booking = Booking::factory()->create([
            'branch_id' => $branch->id,
            'space_id' => $space->id,
            'user_id' => $staff->id,
            'status' => BookingStatus::Pending,
        ]);

        Livewire::actingAs($manager)
            ->test(Index::class)
            ->call('confirmReject', $booking->id)
            ->set('rejectReason', 'Room under maintenance')
            ->call('reject');

        $booking->refresh();
        $this->assertSame(BookingStatus::Rejected, $booking->status);
        $this->assertSame('Room under maintenance', $booking->notes);
    }
}
