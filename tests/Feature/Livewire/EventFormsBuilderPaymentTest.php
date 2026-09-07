<?php

namespace Tests\Feature\Livewire;

use App\Enums\RoleName;
use App\Livewire\EventForms\Builder;
use App\Models\Event;
use App\Models\EventForm;
use App\Models\EventFormField;
use App\Models\Organization;
use App\Models\OrganizationPaymentSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventFormsBuilderPaymentTest extends TestCase
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

    public function test_payment_section_shows_a_notice_when_the_organization_has_no_method_configured(): void
    {
        $organization = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $organization->id]);
        $eventForm = EventForm::factory()->for($event)->create(['organization_id' => $organization->id]);

        Livewire::actingAs($this->admin($organization))
            ->test(Builder::class, ['eventForm' => $eventForm])
            ->assertSee('No payment method connected yet.')
            ->assertDontSee('Require payment to register');
    }

    public function test_admin_can_enable_a_flat_fee(): void
    {
        $organization = Organization::factory()->create();
        OrganizationPaymentSetting::factory()->for($organization)->chipConfigured()->create();

        $event = Event::factory()->create(['organization_id' => $organization->id]);
        $eventForm = EventForm::factory()->for($event)->create(['organization_id' => $organization->id]);

        Livewire::actingAs($this->admin($organization))
            ->test(Builder::class, ['eventForm' => $eventForm])
            ->set('paymentEnabled', true)
            ->set('paymentMethods', ['chip'])
            ->set('paymentPricingModel', 'flat')
            ->set('paymentAmount', '50.00')
            ->call('savePaymentSettings')
            ->assertHasNoErrors();

        $eventForm->refresh();
        $this->assertTrue($eventForm->payment_enabled);
        $this->assertSame(['chip'], $eventForm->payment_methods);
        $this->assertSame('50.00', (string) $eventForm->payment_amount);
        $this->assertNull($eventForm->pricing_field_id);
    }

    public function test_admin_can_configure_tiered_pricing(): void
    {
        $organization = Organization::factory()->create();
        OrganizationPaymentSetting::factory()->for($organization)->chipConfigured()->bankTransferConfigured()->create();

        $event = Event::factory()->create(['organization_id' => $organization->id]);
        $eventForm = EventForm::factory()->for($event)->create(['organization_id' => $organization->id]);
        $ticketField = EventFormField::factory()->for($eventForm, 'eventForm')
            ->choice(['VIP', 'Regular'])
            ->create(['organization_id' => $organization->id, 'label' => 'Ticket Type']);

        Livewire::actingAs($this->admin($organization))
            ->test(Builder::class, ['eventForm' => $eventForm])
            ->set('paymentEnabled', true)
            ->set('paymentMethods', ['chip', 'bank_transfer'])
            ->set('paymentPricingModel', 'tiered')
            ->set('paymentPricingFieldId', $ticketField->id)
            ->set('paymentOptionPrices.VIP', '150.00')
            ->set('paymentOptionPrices.Regular', '50.00')
            ->call('savePaymentSettings')
            ->assertHasNoErrors();

        $eventForm->refresh();
        $ticketField->refresh();

        $this->assertTrue($eventForm->payment_enabled);
        $this->assertSame($ticketField->id, $eventForm->pricing_field_id);
        $this->assertNull($eventForm->payment_amount);
        $this->assertEquals(['VIP' => 150.0, 'Regular' => 50.0], $ticketField->option_prices);

        // The amount resolver reads back exactly what was configured.
        $this->assertSame(150.0, $eventForm->resolveAmountFor([$ticketField->id => 'VIP']));
        $this->assertSame(50.0, $eventForm->resolveAmountFor([$ticketField->id => 'Regular']));
        $this->assertNull($eventForm->resolveAmountFor([$ticketField->id => 'Unknown']));
    }

    public function test_tiered_pricing_requires_a_price_for_every_option(): void
    {
        $organization = Organization::factory()->create();
        OrganizationPaymentSetting::factory()->for($organization)->chipConfigured()->create();

        $event = Event::factory()->create(['organization_id' => $organization->id]);
        $eventForm = EventForm::factory()->for($event)->create(['organization_id' => $organization->id]);
        $ticketField = EventFormField::factory()->for($eventForm, 'eventForm')
            ->choice(['VIP', 'Regular'])
            ->create(['organization_id' => $organization->id, 'label' => 'Ticket Type']);

        Livewire::actingAs($this->admin($organization))
            ->test(Builder::class, ['eventForm' => $eventForm])
            ->set('paymentEnabled', true)
            ->set('paymentMethods', ['chip'])
            ->set('paymentPricingModel', 'tiered')
            ->set('paymentPricingFieldId', $ticketField->id)
            ->set('paymentOptionPrices.VIP', '150.00')
            ->set('paymentOptionPrices.Regular', '')
            ->call('savePaymentSettings')
            ->assertHasErrors(['paymentOptionPrices.Regular']);

        $this->assertFalse($eventForm->refresh()->payment_enabled);
    }

    public function test_enabling_payment_without_a_method_fails_validation(): void
    {
        $organization = Organization::factory()->create();
        OrganizationPaymentSetting::factory()->for($organization)->chipConfigured()->create();

        $event = Event::factory()->create(['organization_id' => $organization->id]);
        $eventForm = EventForm::factory()->for($event)->create(['organization_id' => $organization->id]);

        Livewire::actingAs($this->admin($organization))
            ->test(Builder::class, ['eventForm' => $eventForm])
            ->set('paymentEnabled', true)
            ->set('paymentPricingModel', 'flat')
            ->set('paymentAmount', '10.00')
            ->call('savePaymentSettings')
            ->assertHasErrors(['paymentMethods']);
    }

    public function test_a_method_the_organization_has_not_configured_cannot_be_selected(): void
    {
        $organization = Organization::factory()->create();
        OrganizationPaymentSetting::factory()->for($organization)->chipConfigured()->create();

        $event = Event::factory()->create(['organization_id' => $organization->id]);
        $eventForm = EventForm::factory()->for($event)->create(['organization_id' => $organization->id]);

        Livewire::actingAs($this->admin($organization))
            ->test(Builder::class, ['eventForm' => $eventForm])
            ->set('paymentEnabled', true)
            ->set('paymentMethods', ['bank_transfer'])
            ->set('paymentPricingModel', 'flat')
            ->set('paymentAmount', '10.00')
            ->call('savePaymentSettings')
            ->assertHasErrors(['paymentMethods.0']);
    }

    public function test_flat_fee_resolver_returns_null_when_payment_disabled(): void
    {
        $eventForm = EventForm::factory()->make(['payment_enabled' => false, 'payment_amount' => 50]);

        $this->assertNull($eventForm->resolveAmountFor([]));
    }
}
