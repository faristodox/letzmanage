<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleName;
use App\Models\Portfolio;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_portfolios(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $portfolio = Portfolio::factory()->create();

        $this->assertTrue($admin->can('viewAny', Portfolio::class));
        $this->assertTrue($admin->can('create', Portfolio::class));
        $this->assertTrue($admin->can('update', $portfolio));
        $this->assertTrue($admin->can('delete', $portfolio));
    }

    public function test_manager_and_staff_cannot_manage_portfolios(): void
    {
        $portfolio = Portfolio::factory()->create();

        $manager = User::factory()->create();
        $manager->assignRole(RoleName::Manager->value);

        $staff = User::factory()->create();
        $staff->assignRole(RoleName::Staff->value);

        foreach ([$manager, $staff] as $user) {
            $this->assertFalse($user->can('viewAny', Portfolio::class));
            $this->assertFalse($user->can('create', Portfolio::class));
            $this->assertFalse($user->can('update', $portfolio));
            $this->assertFalse($user->can('delete', $portfolio));
        }
    }
}
