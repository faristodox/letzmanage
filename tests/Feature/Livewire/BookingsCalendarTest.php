<?php

namespace Tests\Feature\Livewire;

use App\Enums\ApprovalMode;
use App\Enums\BookingStatus;
use App\Enums\OfficeSpaceStatus;
use App\Enums\RoleName;
use App\Livewire\Bookings\Calendar;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Event;
use App\Models\EventForm;
use App\Models\OfficeSpace;
use App\Models\User;
use App\Services\SystemSettingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingsCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_calendar_shows_bookings_for_selected_space_and_allows_creating_a_booking(): void
    {
        $branch = Branch::factory()->create();
        $space = OfficeSpace::factory()->create(['branch_id' => $branch->id, 'status' => OfficeSpaceStatus::Active]);

        app(SystemSettingService::class)->setApprovalMode(ApprovalMode::Auto, $branch->id);

        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $staff->assignRole(RoleName::Staff->value);

        $existing = Booking::factory()->create([
            'branch_id' => $branch->id,
            'space_id' => $space->id,
            'user_id' => $staff->id,
            'status' => BookingStatus::Approved,
            'start_time' => now()->addDays(2)->setTime(9, 0),
            'end_time' => now()->addDays(2)->setTime(10, 0),
        ]);

        $date = now()->addDays(5)->format('Y-m-d');

        Livewire::actingAs($staff)
            ->test(Calendar::class)
            ->assertSet('space_id', null)
            ->assertSee($existing->title)
            ->call('openCreate', $date)
            ->set('title', 'Planning session')
            ->set('start_time', '11:00')
            ->set('end_time', '12:00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('bookings', [
            'space_id' => $space->id,
            'title' => 'Planning session',
            'status' => BookingStatus::Approved->value,
        ]);
    }

    public function test_selecting_all_office_spaces_shows_bookings_from_every_space(): void
    {
        $branch = Branch::factory()->create();
        $spaceA = OfficeSpace::factory()->create(['branch_id' => $branch->id, 'status' => OfficeSpaceStatus::Active, 'name' => 'Dewan Utama']);
        $spaceB = OfficeSpace::factory()->create(['branch_id' => $branch->id, 'status' => OfficeSpaceStatus::Active, 'name' => 'Bilik Studio']);

        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole(RoleName::Admin->value);

        $bookingA = Booking::factory()->create([
            'branch_id' => $branch->id,
            'space_id' => $spaceA->id,
            'status' => BookingStatus::Approved,
            'title' => 'Meeting A',
            'start_time' => now()->addDays(2)->setTime(9, 0),
            'end_time' => now()->addDays(2)->setTime(10, 0),
        ]);
        $bookingB = Booking::factory()->create([
            'branch_id' => $branch->id,
            'space_id' => $spaceB->id,
            'status' => BookingStatus::Approved,
            'title' => 'Meeting B',
            'start_time' => now()->addDays(2)->setTime(11, 0),
            'end_time' => now()->addDays(2)->setTime(12, 0),
        ]);

        Livewire::actingAs($admin)
            ->test(Calendar::class)
            ->set('space_id', null)
            ->assertSee($bookingA->title)
            ->assertSee($bookingB->title)
            ->assertSee($spaceA->name)
            ->assertSee($spaceB->name);
    }

    public function test_can_create_a_booking_for_a_specific_space_while_all_spaces_are_selected(): void
    {
        $branch = Branch::factory()->create();
        $spaceA = OfficeSpace::factory()->create(['branch_id' => $branch->id, 'status' => OfficeSpaceStatus::Active]);
        $spaceB = OfficeSpace::factory()->create(['branch_id' => $branch->id, 'status' => OfficeSpaceStatus::Active]);

        app(SystemSettingService::class)->setApprovalMode(ApprovalMode::Auto, $branch->id);

        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $staff->assignRole(RoleName::Staff->value);

        $date = now()->addDays(5)->format('Y-m-d');

        Livewire::actingAs($staff)
            ->test(Calendar::class)
            ->set('space_id', null)
            ->call('openCreate', $date)
            ->set('bookingSpaceId', $spaceB->id)
            ->set('title', 'Planning session')
            ->set('start_time', '11:00')
            ->set('end_time', '12:00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('bookings', [
            'space_id' => $spaceB->id,
            'title' => 'Planning session',
        ]);
    }

    public function test_user_without_create_permission_is_forbidden(): void
    {
        $branch = Branch::factory()->create();

        // A user with no assigned role has none of the booking permissions.
        $user = User::factory()->create(['branch_id' => $branch->id]);

        Livewire::actingAs($user)
            ->test(Calendar::class)
            ->assertForbidden();
    }

    public function test_calendar_shows_events_alongside_bookings_for_users_who_can_view_events(): void
    {
        $branch = Branch::factory()->create();
        $space = OfficeSpace::factory()->create(['branch_id' => $branch->id, 'status' => OfficeSpaceStatus::Active]);

        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole(RoleName::Admin->value);

        $event = Event::factory()->create([
            'title' => 'Annual Dinner 2026',
            'start_date' => now()->addDays(3)->format('Y-m-d'),
        ]);

        Livewire::actingAs($admin)
            ->test(Calendar::class)
            ->assertViewHas('canViewEvents', true)
            ->assertSee($event->title);
    }

    public function test_staff_without_event_permission_never_sees_events(): void
    {
        $branch = Branch::factory()->create();
        OfficeSpace::factory()->create(['branch_id' => $branch->id, 'status' => OfficeSpaceStatus::Active]);

        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $staff->assignRole(RoleName::Staff->value);

        $event = Event::factory()->create([
            'title' => 'Annual Dinner 2026',
            'start_date' => now()->addDays(3)->format('Y-m-d'),
        ]);

        Livewire::actingAs($staff)
            ->test(Calendar::class)
            ->assertViewHas('canViewEvents', false)
            ->set('type', 'event')
            ->assertDontSee($event->title);
    }

    public function test_booking_filter_hides_events(): void
    {
        $branch = Branch::factory()->create();
        $space = OfficeSpace::factory()->create(['branch_id' => $branch->id, 'status' => OfficeSpaceStatus::Active]);

        app(SystemSettingService::class)->setApprovalMode(ApprovalMode::Auto, $branch->id);

        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole(RoleName::Admin->value);

        $booking = Booking::factory()->create([
            'branch_id' => $branch->id,
            'space_id' => $space->id,
            'user_id' => $admin->id,
            'status' => BookingStatus::Approved,
            'title' => 'Team Sync',
            'start_time' => now()->addDays(2)->setTime(9, 0),
            'end_time' => now()->addDays(2)->setTime(10, 0),
        ]);

        $event = Event::factory()->create([
            'title' => 'Annual Dinner 2026',
            'start_date' => now()->addDays(2)->format('Y-m-d'),
        ]);

        Livewire::actingAs($admin)
            ->test(Calendar::class)
            ->set('type', 'booking')
            ->assertSee($booking->title)
            ->assertDontSee($event->title);
    }

    public function test_event_filter_hides_bookings(): void
    {
        $branch = Branch::factory()->create();
        $space = OfficeSpace::factory()->create(['branch_id' => $branch->id, 'status' => OfficeSpaceStatus::Active]);

        app(SystemSettingService::class)->setApprovalMode(ApprovalMode::Auto, $branch->id);

        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole(RoleName::Admin->value);

        $booking = Booking::factory()->create([
            'branch_id' => $branch->id,
            'space_id' => $space->id,
            'user_id' => $admin->id,
            'status' => BookingStatus::Approved,
            'title' => 'Team Sync',
            'start_time' => now()->addDays(2)->setTime(9, 0),
            'end_time' => now()->addDays(2)->setTime(10, 0),
        ]);

        $event = Event::factory()->create([
            'title' => 'Annual Dinner 2026',
            'start_date' => now()->addDays(2)->format('Y-m-d'),
        ]);

        Livewire::actingAs($admin)
            ->test(Calendar::class)
            ->set('type', 'event')
            ->assertSee($event->title)
            ->assertDontSee($booking->title);
    }

    public function test_opening_the_create_modal_defaults_to_the_event_tab_for_users_who_can_view_events(): void
    {
        $branch = Branch::factory()->create();
        OfficeSpace::factory()->create(['branch_id' => $branch->id, 'status' => OfficeSpaceStatus::Active]);

        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole(RoleName::Admin->value);

        $date = now()->addDays(5)->format('Y-m-d');

        Livewire::actingAs($admin)
            ->test(Calendar::class)
            ->call('openCreate', $date)
            ->assertSet('modalTab', 'event')
            ->assertSet('eventStartDate', $date);
    }

    public function test_opening_the_create_modal_defaults_to_the_booking_tab_for_staff(): void
    {
        $branch = Branch::factory()->create();
        OfficeSpace::factory()->create(['branch_id' => $branch->id, 'status' => OfficeSpaceStatus::Active]);

        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $staff->assignRole(RoleName::Staff->value);

        Livewire::actingAs($staff)
            ->test(Calendar::class)
            ->call('openCreate', now()->addDays(5)->format('Y-m-d'))
            ->assertSet('modalTab', 'booking');
    }

    public function test_admin_can_create_an_event_from_the_calendar_modal_and_is_redirected_to_the_builder(): void
    {
        $branch = Branch::factory()->create();
        OfficeSpace::factory()->create(['branch_id' => $branch->id, 'status' => OfficeSpaceStatus::Active]);

        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole(RoleName::Admin->value);

        $date = now()->addDays(5)->format('Y-m-d');

        Livewire::actingAs($admin)
            ->test(Calendar::class)
            ->call('openCreate', $date)
            ->set('eventTitle', 'Planning Retreat')
            ->set('eventLocation', 'Main Hall')
            ->call('saveEvent')
            ->assertHasNoErrors()
            ->assertRedirect();

        $event = Event::where('title', 'Planning Retreat')->first();
        $this->assertNotNull($event);
        $this->assertSame($date, $event->start_date->format('Y-m-d'));
        $this->assertSame('Main Hall', $event->location);
        $this->assertNotNull($event->registrationForm);
    }

    public function test_clicking_a_booking_opens_its_details(): void
    {
        $branch = Branch::factory()->create();
        $space = OfficeSpace::factory()->create(['branch_id' => $branch->id, 'status' => OfficeSpaceStatus::Active, 'name' => 'Dewan Utama']);

        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole(RoleName::Admin->value);

        $booking = Booking::factory()->create([
            'branch_id' => $branch->id,
            'space_id' => $space->id,
            'user_id' => $admin->id,
            'status' => BookingStatus::Approved,
            'title' => 'Team Sync',
            'notes' => 'Bring laptops',
            'start_time' => now()->addDays(2)->setTime(9, 0),
            'end_time' => now()->addDays(2)->setTime(10, 0),
        ]);

        Livewire::actingAs($admin)
            ->test(Calendar::class)
            ->call('viewBooking', $booking->id)
            ->assertSet('viewBookingId', $booking->id)
            ->assertSee('Team Sync')
            ->assertSee('Dewan Utama')
            ->assertSee('Bring laptops');
    }

    public function test_closing_the_booking_view_clears_it(): void
    {
        $branch = Branch::factory()->create();
        $space = OfficeSpace::factory()->create(['branch_id' => $branch->id, 'status' => OfficeSpaceStatus::Active]);

        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole(RoleName::Admin->value);

        $booking = Booking::factory()->create(['branch_id' => $branch->id, 'space_id' => $space->id]);

        Livewire::actingAs($admin)
            ->test(Calendar::class)
            ->call('viewBooking', $booking->id)
            ->call('closeView')
            ->assertSet('viewBookingId', null);
    }

    public function test_clicking_an_event_opens_its_details_with_a_manage_link(): void
    {
        $branch = Branch::factory()->create();
        OfficeSpace::factory()->create(['branch_id' => $branch->id, 'status' => OfficeSpaceStatus::Active]);

        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole(RoleName::Admin->value);

        $event = Event::factory()->create([
            'title' => 'Annual Dinner 2026',
            'start_date' => now()->addDays(3)->format('Y-m-d'),
            'location' => 'Main Hall',
        ]);
        $eventForm = EventForm::factory()->published()->for($event)->create();

        Livewire::actingAs($admin)
            ->test(Calendar::class)
            ->call('viewEvent', $event->id)
            ->assertSet('viewEventId', $event->id)
            ->assertSee('Annual Dinner 2026')
            ->assertSee('Main Hall')
            ->assertSee(route('event-forms.builder', $eventForm), false);
    }

    public function test_manager_can_approve_a_pending_booking_from_the_details_popup(): void
    {
        $branch = Branch::factory()->create();
        $space = OfficeSpace::factory()->create(['branch_id' => $branch->id, 'status' => OfficeSpaceStatus::Active]);

        $manager = User::factory()->create(['branch_id' => $branch->id]);
        $manager->assignRole(RoleName::Manager->value);

        $booking = Booking::factory()->create([
            'branch_id' => $branch->id,
            'space_id' => $space->id,
            'status' => BookingStatus::Pending,
        ]);

        Livewire::actingAs($manager)
            ->test(Calendar::class)
            ->call('viewBooking', $booking->id)
            ->call('confirmApprove')
            ->assertSet('confirmingApprove', true)
            ->set('approveNote', 'See you there')
            ->call('approveBooking')
            ->assertSet('viewBookingId', null);

        $booking->refresh();
        $this->assertSame(BookingStatus::Approved, $booking->status);
        $this->assertSame('See you there', $booking->notes);
    }

    public function test_manager_can_reject_a_pending_booking_from_the_details_popup(): void
    {
        $branch = Branch::factory()->create();
        $space = OfficeSpace::factory()->create(['branch_id' => $branch->id, 'status' => OfficeSpaceStatus::Active]);

        $manager = User::factory()->create(['branch_id' => $branch->id]);
        $manager->assignRole(RoleName::Manager->value);

        $booking = Booking::factory()->create([
            'branch_id' => $branch->id,
            'space_id' => $space->id,
            'status' => BookingStatus::Pending,
        ]);

        Livewire::actingAs($manager)
            ->test(Calendar::class)
            ->call('viewBooking', $booking->id)
            ->call('confirmReject')
            ->assertSet('confirmingReject', true)
            ->set('rejectReason', 'Space unavailable')
            ->call('rejectBooking')
            ->assertSet('viewBookingId', null);

        $booking->refresh();
        $this->assertSame(BookingStatus::Rejected, $booking->status);
        $this->assertSame('Space unavailable', $booking->notes);
    }

    public function test_staff_cannot_approve_a_booking_from_the_details_popup(): void
    {
        $branch = Branch::factory()->create();
        $space = OfficeSpace::factory()->create(['branch_id' => $branch->id, 'status' => OfficeSpaceStatus::Active]);

        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $staff->assignRole(RoleName::Staff->value);

        $booking = Booking::factory()->create([
            'branch_id' => $branch->id,
            'space_id' => $space->id,
            'status' => BookingStatus::Pending,
        ]);

        Livewire::actingAs($staff)
            ->test(Calendar::class)
            ->call('viewBooking', $booking->id)
            ->call('confirmApprove')
            ->assertForbidden();
    }

    public function test_approving_an_already_approved_booking_is_not_offered(): void
    {
        $branch = Branch::factory()->create();
        $space = OfficeSpace::factory()->create(['branch_id' => $branch->id, 'status' => OfficeSpaceStatus::Active]);

        $manager = User::factory()->create(['branch_id' => $branch->id]);
        $manager->assignRole(RoleName::Manager->value);

        $booking = Booking::factory()->create([
            'branch_id' => $branch->id,
            'space_id' => $space->id,
            'status' => BookingStatus::Approved,
        ]);

        Livewire::actingAs($manager)
            ->test(Calendar::class)
            ->call('viewBooking', $booking->id)
            ->assertDontSee('Confirm Approve');
    }

    public function test_multi_day_event_appears_on_every_day_it_spans(): void
    {
        $branch = Branch::factory()->create();
        OfficeSpace::factory()->create(['branch_id' => $branch->id, 'status' => OfficeSpaceStatus::Active]);

        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole(RoleName::Admin->value);

        // Stay comfortably inside the same visible month grid.
        $monthStart = now()->startOfMonth()->addMonth();
        $event = Event::factory()->create([
            'title' => 'Retreat 2026',
            'start_date' => $monthStart->copy()->addDays(9)->format('Y-m-d'),
            'end_date' => $monthStart->copy()->addDays(11)->format('Y-m-d'),
        ]);

        $html = Livewire::actingAs($admin)
            ->test(Calendar::class)
            ->set('month', $monthStart->format('Y-m'))
            ->set('type', 'event')
            ->html();

        // Each day cell renders the title twice (visible text + tooltip
        // attribute), so 3 spanned days => 6 occurrences.
        $this->assertSame(6, substr_count($html, $event->title));
    }
}
