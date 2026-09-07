<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleName;
use App\Models\EventForm;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventFormPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_forms_and_responses(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $eventForm = EventForm::factory()->create();

        $this->assertTrue($admin->can('viewAny', EventForm::class));
        $this->assertTrue($admin->can('create', EventForm::class));
        $this->assertTrue($admin->can('update', $eventForm));
        $this->assertTrue($admin->can('delete', $eventForm));
        $this->assertTrue($admin->can('manageResponses', $eventForm));
    }

    public function test_manager_can_manage_forms_and_responses(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::Manager->value);

        $eventForm = EventForm::factory()->create();

        $this->assertTrue($manager->can('update', $eventForm));
        $this->assertTrue($manager->can('viewResponses', $eventForm));
        $this->assertTrue($manager->can('manageResponses', $eventForm));
    }

    public function test_staff_cannot_manage_or_view_event_forms_by_default(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::Staff->value);

        $eventForm = EventForm::factory()->create();

        $this->assertFalse($staff->can('viewAny', EventForm::class));
        $this->assertFalse($staff->can('create', EventForm::class));
        $this->assertFalse($staff->can('update', $eventForm));
        $this->assertFalse($staff->can('viewResponses', $eventForm));
    }
}
