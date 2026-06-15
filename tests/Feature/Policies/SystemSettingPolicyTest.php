<?php

namespace Tests\Feature\Policies;

use App\Enums\ApprovalMode;
use App\Enums\RoleName;
use App\Enums\SettingKey;
use App\Models\Branch;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_system_settings(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $setting = SystemSetting::factory()->create([
            'branch_id' => null,
            'key' => SettingKey::BookingApprovalMode->value,
            'value' => ApprovalMode::Manual->value,
        ]);

        $this->assertTrue($admin->can('viewAny', SystemSetting::class));
        $this->assertTrue($admin->can('view', $setting));
        $this->assertTrue($admin->can('update', $setting));
    }

    public function test_manager_and_staff_cannot_manage_system_settings(): void
    {
        $branch = Branch::factory()->create();

        $manager = User::factory()->create(['branch_id' => $branch->id]);
        $manager->assignRole(RoleName::Manager->value);

        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $staff->assignRole(RoleName::Staff->value);

        $setting = SystemSetting::factory()->create([
            'branch_id' => null,
            'key' => SettingKey::BookingApprovalMode->value,
            'value' => ApprovalMode::Manual->value,
        ]);

        foreach ([$manager, $staff] as $user) {
            $this->assertFalse($user->can('viewAny', SystemSetting::class));
            $this->assertFalse($user->can('view', $setting));
            $this->assertFalse($user->can('update', $setting));
        }
    }
}
