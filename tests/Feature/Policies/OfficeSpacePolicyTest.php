<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\OfficeSpace;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeSpacePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_office_spaces_in_any_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $admin = User::factory()->create(['branch_id' => $branchA->id]);
        $admin->assignRole(RoleName::Admin->value);

        $spaceInB = OfficeSpace::factory()->create(['branch_id' => $branchB->id]);

        $this->assertTrue($admin->can('create', [OfficeSpace::class, $branchB->id]));
        $this->assertTrue($admin->can('update', $spaceInB));
        $this->assertTrue($admin->can('delete', $spaceInB));
    }

    public function test_manager_can_only_manage_office_spaces_in_their_own_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $manager = User::factory()->create(['branch_id' => $branchA->id]);
        $manager->assignRole(RoleName::Manager->value);

        $spaceInA = OfficeSpace::factory()->create(['branch_id' => $branchA->id]);
        $spaceInB = OfficeSpace::factory()->create(['branch_id' => $branchB->id]);

        $this->assertTrue($manager->can('create', [OfficeSpace::class, $branchA->id]));
        $this->assertTrue($manager->can('update', $spaceInA));

        $this->assertFalse($manager->can('create', [OfficeSpace::class, $branchB->id]));
        $this->assertFalse($manager->can('update', $spaceInB));
    }

    public function test_staff_cannot_manage_office_spaces_but_can_view_them(): void
    {
        $branch = Branch::factory()->create();

        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $staff->assignRole(RoleName::Staff->value);

        $space = OfficeSpace::factory()->create(['branch_id' => $branch->id]);

        $this->assertTrue($staff->can('view', $space));
        $this->assertFalse($staff->can('create', [OfficeSpace::class, $branch->id]));
        $this->assertFalse($staff->can('update', $space));
    }

    public function test_office_space_visible_to_scope_restricts_non_admins_to_their_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        OfficeSpace::factory()->create(['branch_id' => $branchA->id]);
        OfficeSpace::factory()->create(['branch_id' => $branchB->id]);

        $admin = User::factory()->create(['branch_id' => $branchA->id]);
        $admin->assignRole(RoleName::Admin->value);

        $staff = User::factory()->create(['branch_id' => $branchA->id]);
        $staff->assignRole(RoleName::Staff->value);

        $this->assertCount(2, OfficeSpace::visibleTo($admin)->get());
        $this->assertCount(1, OfficeSpace::visibleTo($staff)->get());
        $this->assertEquals($branchA->id, OfficeSpace::visibleTo($staff)->first()->branch_id);
    }
}
