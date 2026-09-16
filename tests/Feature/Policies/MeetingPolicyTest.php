<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleName;
use App\Models\Meeting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_meetings(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $meeting = Meeting::factory()->create();

        $this->assertTrue($admin->can('viewAny', Meeting::class));
        $this->assertTrue($admin->can('create', Meeting::class));
        $this->assertTrue($admin->can('view', $meeting));
        $this->assertTrue($admin->can('delete', $meeting));
    }

    public function test_manager_can_manage_meetings(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::Manager->value);

        $meeting = Meeting::factory()->create();

        $this->assertTrue($manager->can('viewAny', Meeting::class));
        $this->assertTrue($manager->can('create', Meeting::class));
        $this->assertTrue($manager->can('view', $meeting));
        $this->assertTrue($manager->can('delete', $meeting));
    }

    public function test_staff_cannot_manage_meetings_by_default(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::Staff->value);

        $meeting = Meeting::factory()->create();

        $this->assertFalse($staff->can('viewAny', Meeting::class));
        $this->assertFalse($staff->can('create', Meeting::class));
        $this->assertFalse($staff->can('view', $meeting));
        $this->assertFalse($staff->can('delete', $meeting));
    }
}
