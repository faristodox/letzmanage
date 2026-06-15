<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\OfficeSpace;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_view_all_pages(): void
    {
        $branch = Branch::factory()->create();
        OfficeSpace::factory()->create(['branch_id' => $branch->id]);

        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole(RoleName::Admin->value);

        foreach (['dashboard', 'branches', 'office-spaces', 'users', 'settings', 'bookings', 'bookings/calendar', 'profile'] as $page) {
            $this->actingAs($admin)->get('/'.$page)->assertOk();
        }
    }

    public function test_staff_can_view_allowed_pages_and_is_forbidden_from_admin_pages(): void
    {
        $branch = Branch::factory()->create();
        OfficeSpace::factory()->create(['branch_id' => $branch->id]);

        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $staff->assignRole(RoleName::Staff->value);

        foreach (['dashboard', 'office-spaces', 'bookings', 'bookings/calendar', 'profile'] as $page) {
            $this->actingAs($staff)->get('/'.$page)->assertOk();
        }

        foreach (['branches', 'users', 'settings'] as $page) {
            $this->actingAs($staff)->get('/'.$page)->assertForbidden();
        }
    }

    public function test_manager_can_manage_office_spaces_in_their_branch(): void
    {
        $branch = Branch::factory()->create();

        $manager = User::factory()->create(['branch_id' => $branch->id]);
        $manager->assignRole(RoleName::Manager->value);

        foreach (['dashboard', 'office-spaces', 'bookings', 'bookings/calendar', 'profile'] as $page) {
            $this->actingAs($manager)->get('/'.$page)->assertOk();
        }

        foreach (['branches', 'users', 'settings'] as $page) {
            $this->actingAs($manager)->get('/'.$page)->assertForbidden();
        }
    }
}
