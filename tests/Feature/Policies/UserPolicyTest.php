<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_other_users_but_not_delete_self(): void
    {
        $branch = Branch::factory()->create();

        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole(RoleName::Admin->value);

        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $staff->assignRole(RoleName::Staff->value);

        $this->assertTrue($admin->can('viewAny', User::class));
        $this->assertTrue($admin->can('view', $staff));
        $this->assertTrue($admin->can('create', User::class));
        $this->assertTrue($admin->can('update', $staff));
        $this->assertTrue($admin->can('delete', $staff));

        $this->assertFalse($admin->can('delete', $admin));
    }

    public function test_manager_and_staff_cannot_manage_users(): void
    {
        $branch = Branch::factory()->create();

        $manager = User::factory()->create(['branch_id' => $branch->id]);
        $manager->assignRole(RoleName::Manager->value);

        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $staff->assignRole(RoleName::Staff->value);

        $other = User::factory()->create(['branch_id' => $branch->id]);
        $other->assignRole(RoleName::Staff->value);

        foreach ([$manager, $staff] as $user) {
            $this->assertFalse($user->can('viewAny', User::class));
            $this->assertFalse($user->can('view', $other));
            $this->assertFalse($user->can('create', User::class));
            $this->assertFalse($user->can('update', $other));
            $this->assertFalse($user->can('delete', $other));
        }
    }
}
