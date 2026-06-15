<?php

namespace Tests\Feature\Livewire;

use App\Enums\ApprovalMode;
use App\Enums\BookingStatus;
use App\Enums\OfficeSpaceStatus;
use App\Enums\RoleName;
use App\Livewire\Bookings\Calendar;
use App\Models\Booking;
use App\Models\Branch;
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
            ->assertSet('space_id', $space->id)
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

    public function test_user_without_create_permission_is_forbidden(): void
    {
        $branch = Branch::factory()->create();

        // A user with no assigned role has none of the booking permissions.
        $user = User::factory()->create(['branch_id' => $branch->id]);

        Livewire::actingAs($user)
            ->test(Calendar::class)
            ->assertForbidden();
    }
}
