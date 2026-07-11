<?php

namespace Tests\Feature\Tenancy;

use App\Enums\RoleName;
use App\Livewire\Admin\Organizations\Index as OrganizationsIndex;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SpiFeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function adminFor(Organization $org): User
    {
        $branch = Branch::factory()->for($org)->create();
        $user = User::factory()->for($org)->create(['branch_id' => $branch->id]);
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }

    public function test_spi_page_is_forbidden_when_org_feature_disabled(): void
    {
        $org = Organization::factory()->create(['spi_enabled' => false]);

        $this->actingAs($this->adminFor($org))
            ->get(route('spi-members.index'))
            ->assertForbidden();
    }

    public function test_spi_page_is_accessible_when_org_feature_enabled(): void
    {
        $org = Organization::factory()->create(['spi_enabled' => true]);

        $this->actingAs($this->adminFor($org))
            ->get(route('spi-members.index'))
            ->assertOk();
    }

    public function test_spi_nav_link_hidden_when_disabled_and_shown_when_enabled(): void
    {
        $org = Organization::factory()->create(['spi_enabled' => false]);
        $admin = $this->adminFor($org);

        $this->actingAs($admin)->get(route('dashboard'))->assertDontSee('Data Ahli (SPI)');

        $org->update(['spi_enabled' => true]);

        $this->actingAs($admin)->get(route('dashboard'))->assertSee('Data Ahli (SPI)');
    }

    public function test_super_admin_can_toggle_spi_for_an_organization(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true, 'organization_id' => null]);
        $org = Organization::factory()->create(['spi_enabled' => false]);

        Livewire::actingAs($superAdmin)
            ->test(OrganizationsIndex::class)
            ->call('toggleSpi', $org->id);

        $this->assertTrue($org->fresh()->spi_enabled);
    }
}
