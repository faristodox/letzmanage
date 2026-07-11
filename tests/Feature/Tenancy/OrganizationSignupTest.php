<?php

namespace Tests\Feature\Tenancy;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\OfficeSpaceType;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationProvisioner;
use App\Services\PlatformSettingService;
use App\Support\CurrentOrganization;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class OrganizationSignupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_provisioner_creates_org_with_defaults_and_admin(): void
    {
        $user = app(OrganizationProvisioner::class)->provision('Acme Co', [
            'name' => 'Ada Admin',
            'email' => 'ada@acme.test',
            'password' => 'password123',
        ]);

        $org = Organization::where('slug', 'acme-co')->first();
        $this->assertNotNull($org);

        $this->assertSame($org->id, $user->organization_id);
        $this->assertTrue($user->hasRole(RoleName::Admin->value));
        $this->assertFalse($user->is_super_admin);

        // Defaults are seeded and scoped to the new org.
        app(CurrentOrganization::class)->set($org);
        $this->assertSame(1, Branch::where('name', 'Head Office')->count());
        $this->assertSame(3, OfficeSpaceType::count());
    }

    public function test_slugs_are_unique_across_organizations(): void
    {
        $provisioner = app(OrganizationProvisioner::class);

        $provisioner->provision('Acme Co', ['name' => 'A', 'email' => 'a@x.test', 'password' => 'password123']);
        $provisioner->provision('Acme Co', ['name' => 'B', 'email' => 'b@x.test', 'password' => 'password123']);

        $slugs = Organization::orderBy('id')->pluck('slug')->all();
        $this->assertSame(['acme-co', 'acme-co-2'], $slugs);
    }

    public function test_public_signup_creates_organization_and_logs_in(): void
    {
        Volt::test('pages.auth.register')
            ->set('organization_name', 'Bright Labs')
            ->set('name', 'Bea Boss')
            ->set('email', 'bea@bright.test')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('organizations', ['name' => 'Bright Labs', 'slug' => 'bright-labs']);
        $this->assertAuthenticated();

        $user = User::withoutGlobalScopes()->where('email', 'bea@bright.test')->first();
        $this->assertNotNull($user->organization_id);
    }

    public function test_public_signup_is_blocked_when_disabled(): void
    {
        app(PlatformSettingService::class)->setPublicSignupEnabled(false);

        // The register page aborts at mount when signup is disabled.
        $this->get(route('register'))->assertForbidden();
    }
}
