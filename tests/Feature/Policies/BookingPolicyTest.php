<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleName;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\OfficeSpace;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_staff_can_only_view_and_cancel_their_own_bookings(): void
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
        ]);

        $othersBooking = Booking::factory()->create([
            'branch_id' => $branch->id,
            'space_id' => $space->id,
            'user_id' => $otherStaff->id,
        ]);

        $this->assertTrue($staff->can('view', $ownBooking));
        $this->assertTrue($staff->can('cancel', $ownBooking));

        $this->assertFalse($staff->can('view', $othersBooking));
        $this->assertFalse($staff->can('cancel', $othersBooking));
        $this->assertFalse($staff->can('approve', $ownBooking));
    }

    public function test_manager_can_view_and_approve_bookings_in_their_own_branch_only(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $spaceA = OfficeSpace::factory()->create(['branch_id' => $branchA->id]);
        $spaceB = OfficeSpace::factory()->create(['branch_id' => $branchB->id]);

        $manager = User::factory()->create(['branch_id' => $branchA->id]);
        $manager->assignRole(RoleName::Manager->value);

        $staffA = User::factory()->create(['branch_id' => $branchA->id]);
        $staffA->assignRole(RoleName::Staff->value);

        $staffB = User::factory()->create(['branch_id' => $branchB->id]);
        $staffB->assignRole(RoleName::Staff->value);

        $bookingA = Booking::factory()->create([
            'branch_id' => $branchA->id,
            'space_id' => $spaceA->id,
            'user_id' => $staffA->id,
        ]);

        $bookingB = Booking::factory()->create([
            'branch_id' => $branchB->id,
            'space_id' => $spaceB->id,
            'user_id' => $staffB->id,
        ]);

        $this->assertTrue($manager->can('view', $bookingA));
        $this->assertTrue($manager->can('approve', $bookingA));
        $this->assertTrue($manager->can('reject', $bookingA));
        $this->assertTrue($manager->can('cancel', $bookingA));

        $this->assertFalse($manager->can('view', $bookingB));
        $this->assertFalse($manager->can('approve', $bookingB));
        $this->assertFalse($manager->can('cancel', $bookingB));
    }

    public function test_admin_can_view_and_approve_bookings_in_any_branch(): void
    {
        $branch = Branch::factory()->create();
        $space = OfficeSpace::factory()->create(['branch_id' => $branch->id]);

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $staff->assignRole(RoleName::Staff->value);

        $booking = Booking::factory()->create([
            'branch_id' => $branch->id,
            'space_id' => $space->id,
            'user_id' => $staff->id,
        ]);

        $this->assertTrue($admin->can('view', $booking));
        $this->assertTrue($admin->can('approve', $booking));
        $this->assertTrue($admin->can('reject', $booking));
        $this->assertTrue($admin->can('cancel', $booking));
    }

    public function test_booking_visible_to_scope_matches_policy_rules(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $spaceA = OfficeSpace::factory()->create(['branch_id' => $branchA->id]);
        $spaceB = OfficeSpace::factory()->create(['branch_id' => $branchB->id]);

        $manager = User::factory()->create(['branch_id' => $branchA->id]);
        $manager->assignRole(RoleName::Manager->value);

        $staffA = User::factory()->create(['branch_id' => $branchA->id]);
        $staffA->assignRole(RoleName::Staff->value);

        $staffB = User::factory()->create(['branch_id' => $branchB->id]);
        $staffB->assignRole(RoleName::Staff->value);

        Booking::factory()->create(['branch_id' => $branchA->id, 'space_id' => $spaceA->id, 'user_id' => $staffA->id]);
        Booking::factory()->create(['branch_id' => $branchB->id, 'space_id' => $spaceB->id, 'user_id' => $staffB->id]);

        // Manager sees only their branch's bookings.
        $this->assertCount(1, Booking::visibleTo($manager)->get());

        // Staff sees only their own bookings.
        $this->assertCount(1, Booking::visibleTo($staffA)->get());
        $this->assertEquals($staffA->id, Booking::visibleTo($staffA)->first()->user_id);
    }
}
