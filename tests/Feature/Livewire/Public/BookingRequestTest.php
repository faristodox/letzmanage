<?php

namespace Tests\Feature\Livewire\Public;

use App\Enums\ApprovalMode;
use App\Enums\BookingStatus;
use App\Enums\RoleName;
use App\Livewire\Public\BookingRequest;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\OfficeSpace;
use App\Models\User;
use App\Notifications\BookingAutoApprovedNotification;
use App\Notifications\BookingSubmittedNotification;
use App\Notifications\GuestBookingReceivedNotification;
use App\Services\SystemSettingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class BookingRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guest_can_submit_a_booking_request_pending_review(): void
    {
        Notification::fake();

        $branch = Branch::factory()->create();
        $space = OfficeSpace::factory()->create(['branch_id' => $branch->id]);

        $manager = User::factory()->create(['branch_id' => $branch->id]);
        $manager->assignRole(RoleName::Manager->value);

        $date = now()->addDay()->format('Y-m-d');

        Livewire::test(BookingRequest::class)
            ->assertSet('branch_id', $branch->id)
            ->assertSet('step', 1)
            ->call('selectSpace', $space->id)
            ->assertSet('space_id', $space->id)
            ->assertSet('step', 2)
            ->call('selectDay', $date)
            ->call('selectSlot', '09:00')
            ->assertSet('start_time', '09:00')
            ->assertSet('end_time', '')
            ->call('selectEndSlot', '10:00')
            ->assertSet('end_time', '10:00')
            ->call('proceedToDetails')
            ->assertHasNoErrors()
            ->assertSet('step', 3)
            ->set('guest_name', 'Jane Visitor')
            ->set('guest_email', 'jane@example.com')
            ->set('guest_phone', '555-1234')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $booking = Booking::where('space_id', $space->id)->first();
        $this->assertNotNull($booking);
        $this->assertNull($booking->user_id);
        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertSame('Jane Visitor', $booking->guest_name);
        $this->assertSame('jane@example.com', $booking->guest_email);
        $this->assertSame("{$date} 09:00:00", $booking->start_time->format('Y-m-d H:i:s'));
        $this->assertSame("{$date} 10:00:00", $booking->end_time->format('Y-m-d H:i:s'));

        Notification::assertSentTo($manager, BookingSubmittedNotification::class);
        Notification::assertSentOnDemand(GuestBookingReceivedNotification::class);
    }

    public function test_guest_booking_is_auto_approved_when_branch_approval_mode_is_auto(): void
    {
        Notification::fake();

        $branch = Branch::factory()->create();
        $space = OfficeSpace::factory()->create(['branch_id' => $branch->id]);

        $manager = User::factory()->create(['branch_id' => $branch->id]);
        $manager->assignRole(RoleName::Manager->value);

        app(SystemSettingService::class)->setApprovalMode(ApprovalMode::Auto, $branch->id);

        $date = now()->addDay()->format('Y-m-d');

        Livewire::test(BookingRequest::class)
            ->call('selectSpace', $space->id)
            ->call('selectDay', $date)
            ->call('selectSlot', '09:00')
            ->call('selectEndSlot', '10:00')
            ->call('proceedToDetails')
            ->assertSet('step', 3)
            ->set('guest_name', 'Jane Visitor')
            ->set('guest_email', 'jane@example.com')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $booking = Booking::where('space_id', $space->id)->first();
        $this->assertSame(BookingStatus::Approved, $booking->status);

        Notification::assertSentTo($manager, BookingAutoApprovedNotification::class);
        Notification::assertSentOnDemand(GuestBookingReceivedNotification::class);
    }

    public function test_overlapping_guest_booking_is_rejected_with_conflict_error(): void
    {
        $branch = Branch::factory()->create();
        $space = OfficeSpace::factory()->create(['branch_id' => $branch->id]);

        $date = now()->addDay()->format('Y-m-d');

        Booking::factory()->create([
            'branch_id' => $branch->id,
            'space_id' => $space->id,
            'status' => BookingStatus::Approved,
            'start_time' => "{$date} 09:00:00",
            'end_time' => "{$date} 10:00:00",
        ]);

        $component = Livewire::test(BookingRequest::class)
            ->call('selectSpace', $space->id)
            ->call('selectDay', $date)
            ->call('selectSlot', '09:00')
            ->call('selectEndSlot', '10:00')
            ->call('proceedToDetails');

        $component->assertSet('step', 2);
        $this->assertNotNull($component->get('errorMessage'));
        $this->assertSame(0, Booking::where('space_id', $space->id)->where('status', BookingStatus::Pending)->count());
    }
}
