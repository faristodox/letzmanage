<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_branches(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $branch = Branch::factory()->create();

        $this->assertTrue($admin->can('viewAny', Branch::class));
        $this->assertTrue($admin->can('create', Branch::class));
        $this->assertTrue($admin->can('update', $branch));
        $this->assertTrue($admin->can('delete', $branch));
    }

    public function test_manager_and_staff_cannot_manage_branches(): void
    {
        $branch = Branch::factory()->create();

        $manager = User::factory()->create(['branch_id' => $branch->id]);
        $manager->assignRole(RoleName::Manager->value);

        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $staff->assignRole(RoleName::Staff->value);

        foreach ([$manager, $staff] as $user) {
            $this->assertFalse($user->can('viewAny', Branch::class));
            $this->assertFalse($user->can('create', Branch::class));
            $this->assertFalse($user->can('update', $branch));
            $this->assertFalse($user->can('delete', $branch));
        }
    }
}
