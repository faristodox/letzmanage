<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleName;
use App\Models\EventForm;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCheckInPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_view_and_manage_and_undo_checkin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $eventForm = EventForm::factory()->create();

        $this->assertTrue($admin->can('viewCheckIn', $eventForm));
        $this->assertTrue($admin->can('manualCheckIn', $eventForm));
        $this->assertTrue($admin->can('undoCheckIn', $eventForm));
    }

    public function test_door_staff_with_only_checkin_permission_can_check_in_but_not_undo(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::Staff->value);
        $staff->givePermissionTo('check in event participants');

        $eventForm = EventForm::factory()->create();

        $this->assertTrue($staff->can('viewCheckIn', $eventForm));
        $this->assertTrue($staff->can('manualCheckIn', $eventForm));
        $this->assertFalse($staff->can('undoCheckIn', $eventForm));
    }

    public function test_staff_without_any_event_form_permission_cannot_check_in(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::Staff->value);

        $eventForm = EventForm::factory()->create();

        $this->assertFalse($staff->can('viewCheckIn', $eventForm));
        $this->assertFalse($staff->can('manualCheckIn', $eventForm));
    }
}
