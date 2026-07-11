<?php

namespace Tests\Feature\Admin;

use App\Enums\OrganizationStatus;
use App\Enums\RoleName;
use App\Livewire\Admin\Organizations\Index;
use App\Models\Organization;
use App\Models\User;
use App\Services\PlatformSettingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true, 'organization_id' => null]);
    }

    public function test_super_admin_can_view_the_platform_organizations_page(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('admin.organizations.index'))
            ->assertOk();
    }

    public function test_non_super_admin_is_forbidden(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create();
        $user->assignRole(RoleName::Admin->value);

        $this->actingAs($user)
            ->get(route('admin.organizations.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_create_an_organization(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(Index::class)
            ->call('create')
            ->set('organization_name', 'Fresh Org')
            ->set('admin_name', 'Owner One')
            ->set('admin_email', 'owner@fresh.test')
            ->set('admin_password', 'password123')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('organizations', ['name' => 'Fresh Org', 'slug' => 'fresh-org']);
        $this->assertDatabaseHas('users', ['email' => 'owner@fresh.test']);
    }

    public function test_super_admin_can_toggle_public_signup(): void
    {
        $settings = app(PlatformSettingService::class);
        $this->assertTrue($settings->isPublicSignupEnabled());

        Livewire::actingAs($this->superAdmin())
            ->test(Index::class)
            ->call('togglePublicSignup')
            ->assertSet('publicSignupEnabled', false);

        $this->assertFalse(app(PlatformSettingService::class)->isPublicSignupEnabled());
    }

    public function test_super_admin_can_suspend_and_reactivate_an_organization(): void
    {
        $org = Organization::factory()->create(['status' => OrganizationStatus::Active]);

        Livewire::actingAs($this->superAdmin())
            ->test(Index::class)
            ->call('toggleStatus', $org->id);

        $this->assertSame(OrganizationStatus::Suspended, $org->fresh()->status);
    }
}
