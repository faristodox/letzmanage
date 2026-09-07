<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleName;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_events(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $event = Event::factory()->create();

        $this->assertTrue($admin->can('viewAny', Event::class));
        $this->assertTrue($admin->can('create', Event::class));
        $this->assertTrue($admin->can('update', $event));
        $this->assertTrue($admin->can('delete', $event));
        $this->assertTrue($admin->can('viewFinances', $event));
        $this->assertTrue($admin->can('manageFinances', $event));
    }

    public function test_staff_cannot_manage_events_by_default(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::Staff->value);

        $event = Event::factory()->create();

        $this->assertFalse($staff->can('viewAny', Event::class));
        $this->assertFalse($staff->can('create', Event::class));
        $this->assertFalse($staff->can('update', $event));
        $this->assertFalse($staff->can('viewFinances', $event));
        $this->assertFalse($staff->can('manageFinances', $event));
    }

    public function test_view_only_role_can_view_but_not_manage_finances(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('view event responses');

        $event = Event::factory()->create();

        $this->assertTrue($viewer->can('viewFinances', $event));
        $this->assertFalse($viewer->can('manageFinances', $event));
    }
}
