<?php

namespace Tests\Feature\Livewire;

use App\Enums\RoleName;
use App\Livewire\Settings\Payments;
use App\Models\Organization;
use App\Models\OrganizationPaymentSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(Organization $organization): User
    {
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $admin->assignRole(RoleName::Admin->value);

        return $admin;
    }

    public function test_admin_can_connect_chip(): void
    {
        $organization = Organization::factory()->create();

        Livewire::actingAs($this->admin($organization))
            ->test(Payments::class)
            ->set('paymentGatewayEnabled', true)
            ->set('chipBrandId', 'brand-123')
            ->set('chipSecretKey', 'sk_test_abc123')
            ->call('save')
            ->assertHasNoErrors();

        $setting = OrganizationPaymentSetting::where('organization_id', $organization->id)->first();
        $this->assertNotNull($setting);
        $this->assertTrue($setting->payment_gateway_enabled);
        $this->assertSame('brand-123', $setting->chip_brand_id);
        $this->assertSame('sk_test_abc123', $setting->chip_secret_key);

        // Stored encrypted at rest, not as plaintext.
        $raw = DB::table('organization_payment_settings')->where('id', $setting->id)->value('chip_secret_key');
        $this->assertStringNotContainsString('sk_test_abc123', $raw);
    }

    public function test_admin_can_configure_bank_transfer(): void
    {
        $organization = Organization::factory()->create();

        Livewire::actingAs($this->admin($organization))
            ->test(Payments::class)
            ->set('bankTransferEnabled', true)
            ->set('bankName', 'Maybank')
            ->set('bankAccountNumber', '1234567890')
            ->set('bankAccountHolder', 'IKRAM Setiawangsa')
            ->call('save')
            ->assertHasNoErrors();

        $setting = OrganizationPaymentSetting::where('organization_id', $organization->id)->first();
        $this->assertTrue($setting->bank_transfer_enabled);
        $this->assertSame('Maybank', $setting->bank_name);
        $this->assertSame('1234567890', $setting->bank_account_number);
        $this->assertSame('IKRAM Setiawangsa', $setting->bank_account_holder);
    }

    public function test_enabling_chip_without_a_secret_key_fails_validation(): void
    {
        $organization = Organization::factory()->create();

        Livewire::actingAs($this->admin($organization))
            ->test(Payments::class)
            ->set('paymentGatewayEnabled', true)
            ->set('chipBrandId', 'brand-123')
            ->call('save')
            ->assertHasErrors(['chipSecretKey']);
    }

    public function test_leaving_secret_key_blank_on_resave_keeps_the_existing_one(): void
    {
        $organization = Organization::factory()->create();
        OrganizationPaymentSetting::factory()->for($organization)->chipConfigured()->create([
            'chip_secret_key' => 'sk_original',
        ]);

        Livewire::actingAs($this->admin($organization))
            ->test(Payments::class)
            ->assertSet('hasStoredChipSecretKey', true)
            ->set('chipBrandId', 'brand-updated')
            ->call('save')
            ->assertHasNoErrors();

        $setting = OrganizationPaymentSetting::where('organization_id', $organization->id)->first();
        $this->assertSame('brand-updated', $setting->chip_brand_id);
        $this->assertSame('sk_original', $setting->chip_secret_key);
    }

    public function test_entering_a_new_secret_key_replaces_the_existing_one(): void
    {
        $organization = Organization::factory()->create();
        OrganizationPaymentSetting::factory()->for($organization)->chipConfigured()->create([
            'chip_secret_key' => 'sk_original',
        ]);

        Livewire::actingAs($this->admin($organization))
            ->test(Payments::class)
            ->set('chipSecretKey', 'sk_replacement')
            ->call('save')
            ->assertHasNoErrors();

        $setting = OrganizationPaymentSetting::where('organization_id', $organization->id)->first();
        $this->assertSame('sk_replacement', $setting->chip_secret_key);
    }

    public function test_staff_cannot_access_payment_settings(): void
    {
        $organization = Organization::factory()->create();
        $staff = User::factory()->create(['organization_id' => $organization->id]);
        $staff->assignRole(RoleName::Staff->value);

        Livewire::actingAs($staff)
            ->test(Payments::class)
            ->assertForbidden();
    }
}
