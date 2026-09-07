<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleName;
use App\Models\Form;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormPolicyTest extends TestCase
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

        $form = Form::factory()->create();

        $this->assertTrue($admin->can('viewAny', Form::class));
        $this->assertTrue($admin->can('create', Form::class));
        $this->assertTrue($admin->can('update', $form));
        $this->assertTrue($admin->can('delete', $form));
        $this->assertTrue($admin->can('viewResponses', $form));
        $this->assertTrue($admin->can('manageResponses', $form));
    }

    public function test_manager_can_manage_forms_and_responses(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::Manager->value);

        $form = Form::factory()->create();

        $this->assertTrue($manager->can('update', $form));
        $this->assertTrue($manager->can('viewResponses', $form));
        $this->assertTrue($manager->can('manageResponses', $form));
    }

    public function test_staff_cannot_manage_or_view_forms_by_default(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::Staff->value);

        $form = Form::factory()->create();

        $this->assertFalse($staff->can('viewAny', Form::class));
        $this->assertFalse($staff->can('create', Form::class));
        $this->assertFalse($staff->can('update', $form));
        $this->assertFalse($staff->can('viewResponses', $form));
    }
}
