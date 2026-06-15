<?php

namespace Tests\Feature\Livewire;

use App\Enums\ApprovalMode;
use App\Enums\RoleName;
use App\Livewire\Settings\Index;
use App\Models\Branch;
use App\Models\User;
use App\Services\SystemSettingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_update_global_and_per_branch_approval_modes(): void
    {
        $branch = Branch::factory()->create();

        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->set('globalMode', ApprovalMode::Auto->value)
            ->set("branchModes.{$branch->id}", ApprovalMode::Manual->value)
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(SystemSettingService::class);

        $this->assertSame(ApprovalMode::Auto, $settings->getApprovalMode());
        $this->assertSame(ApprovalMode::Manual, $settings->getApprovalMode($branch->id));
    }

    public function test_admin_can_update_approval_email_notes(): void
    {
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();

        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->set('globalApprovalNote', 'Please check in at the front desk.')
            ->set("branchApprovalNotes.{$branch->id}", 'Parking is available at the back entrance.')
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(SystemSettingService::class);

        $this->assertSame('Please check in at the front desk.', $settings->getApprovalEmailNote());
        $this->assertSame('Parking is available at the back entrance.', $settings->getApprovalEmailNote($branch->id));
        // Branch without an override falls back to the global default.
        $this->assertSame('Please check in at the front desk.', $settings->getApprovalEmailNote($otherBranch->id));
    }

    public function test_staff_cannot_access_settings_component(): void
    {
        $branch = Branch::factory()->create();

        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $staff->assignRole(RoleName::Staff->value);

        Livewire::actingAs($staff)
            ->test(Index::class)
            ->assertForbidden();
    }
}
