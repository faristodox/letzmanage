<?php

namespace Tests\Feature\Livewire\Public;

use App\Enums\EventFormFieldType;
use App\Enums\EventPaymentMethod;
use App\Enums\EventPaymentStatus;
use App\Livewire\Public\EventRegistration;
use App\Models\Event;
use App\Models\EventForm;
use App\Models\EventFormField;
use App\Models\EventFormResponse;
use App\Models\EventPayment;
use App\Models\Organization;
use App\Models\OrganizationPaymentSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EventRegistrationPaymentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: EventForm, 1: EventFormField}
     */
    private function flatFeeForm(Organization $organization, array $overrides = []): array
    {
        $event = Event::factory()->create(['organization_id' => $organization->id]);
        $eventForm = EventForm::factory()->published()->for($event)->create([
            'organization_id' => $organization->id,
            'payment_enabled' => true,
            'payment_methods' => ['chip', 'bank_transfer'],
            'payment_amount' => 50,
            ...$overrides,
        ]);
        $emailField = EventFormField::factory()->for($eventForm, 'eventForm')->create([
            'organization_id' => $organization->id,
            'label' => 'Email',
            'type' => EventFormFieldType::Email,
            'required' => true,
        ]);

        return [$eventForm, $emailField];
    }

    public function test_submitting_a_form_with_a_flat_fee_moves_to_the_payment_step(): void
    {
        $organization = Organization::factory()->create();
        OrganizationPaymentSetting::factory()->for($organization)->chipConfigured()->bankTransferConfigured()->create();
        [$eventForm, $emailField] = $this->flatFeeForm($organization);

        Livewire::test(EventRegistration::class, ['eventForm' => $eventForm])
            ->set("answers.{$emailField->id}", 'ahmad@example.com')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('step', 'payment')
            ->assertSet('paymentAmountDue', 50.0)
            ->assertSee('CHIP')
            ->assertSee('Bank Transfer');

        $this->assertSame(1, EventFormResponse::count());
    }

    public function test_paying_with_chip_creates_a_pending_payment_and_redirects(): void
    {
        Http::fake([
            'https://gate.chip-in.asia/api/v1/purchases/' => Http::response([
                'id' => 'chip-purchase-123',
                'checkout_url' => 'https://gate.chip-in.asia/checkout/chip-purchase-123',
            ], 201),
        ]);

        $organization = Organization::factory()->create();
        OrganizationPaymentSetting::factory()->for($organization)->chipConfigured()->create();
        [$eventForm, $emailField] = $this->flatFeeForm($organization, ['payment_methods' => ['chip']]);

        Livewire::test(EventRegistration::class, ['eventForm' => $eventForm])
            ->set("answers.{$emailField->id}", 'ahmad@example.com')
            ->call('submit')
            ->assertSet('step', 'payment')
            ->call('payWithChip')
            ->assertRedirect('https://gate.chip-in.asia/checkout/chip-purchase-123');

        $response = EventFormResponse::first();
        $payment = EventPayment::where('event_form_response_id', $response->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame(EventPaymentMethod::Chip, $payment->method);
        $this->assertSame(EventPaymentStatus::Pending, $payment->status);
        $this->assertSame('chip-purchase-123', $payment->chip_purchase_id);
        $this->assertSame('50.00', (string) $payment->amount);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://gate.chip-in.asia/api/v1/purchases/'
                && $request['client']['email'] === 'ahmad@example.com'
                && $request['purchase']['products'][0]['price'] === 5000;
        });
    }

    public function test_chip_failure_shows_a_friendly_error_and_does_not_crash(): void
    {
        Http::fake([
            'https://gate.chip-in.asia/api/v1/purchases/' => Http::response(['error' => 'invalid'], 422),
        ]);

        $organization = Organization::factory()->create();
        OrganizationPaymentSetting::factory()->for($organization)->chipConfigured()->create();
        [$eventForm, $emailField] = $this->flatFeeForm($organization, ['payment_methods' => ['chip']]);

        Livewire::test(EventRegistration::class, ['eventForm' => $eventForm])
            ->set("answers.{$emailField->id}", 'ahmad@example.com')
            ->call('submit')
            ->call('payWithChip')
            ->assertSet('step', 'payment')
            ->assertSee('Could not start payment');

        $this->assertSame(0, EventPayment::count());
    }

    public function test_submitting_a_bank_transfer_receipt_creates_a_pending_payment(): void
    {
        Storage::fake('public');

        $organization = Organization::factory()->create();
        OrganizationPaymentSetting::factory()->for($organization)->bankTransferConfigured()->create();
        [$eventForm, $emailField] = $this->flatFeeForm($organization, ['payment_methods' => ['bank_transfer']]);

        Livewire::test(EventRegistration::class, ['eventForm' => $eventForm])
            ->set("answers.{$emailField->id}", 'ahmad@example.com')
            ->call('submit')
            ->assertSet('step', 'payment')
            ->set('paymentReceipt', UploadedFile::fake()->image('receipt.jpg'))
            ->call('submitBankTransfer')
            ->assertHasNoErrors()
            ->assertSet('step', 'done')
            ->assertSee('pending review');

        $response = EventFormResponse::first();
        $payment = EventPayment::where('event_form_response_id', $response->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame(EventPaymentMethod::BankTransfer, $payment->method);
        $this->assertSame(EventPaymentStatus::Pending, $payment->status);
        $this->assertNotNull($payment->receipt_path);
        Storage::disk('public')->assertExists($payment->receipt_path);
    }

    public function test_bank_transfer_receipt_is_required(): void
    {
        $organization = Organization::factory()->create();
        OrganizationPaymentSetting::factory()->for($organization)->bankTransferConfigured()->create();
        [$eventForm, $emailField] = $this->flatFeeForm($organization, ['payment_methods' => ['bank_transfer']]);

        Livewire::test(EventRegistration::class, ['eventForm' => $eventForm])
            ->set("answers.{$emailField->id}", 'ahmad@example.com')
            ->call('submit')
            ->call('submitBankTransfer')
            ->assertHasErrors(['paymentReceipt']);
    }

    public function test_tiered_pricing_resolves_the_correct_amount(): void
    {
        $organization = Organization::factory()->create();
        OrganizationPaymentSetting::factory()->for($organization)->chipConfigured()->create();

        $event = Event::factory()->create(['organization_id' => $organization->id]);
        $eventForm = EventForm::factory()->published()->for($event)->create(['organization_id' => $organization->id]);
        $emailField = EventFormField::factory()->for($eventForm, 'eventForm')->create([
            'organization_id' => $organization->id, 'type' => EventFormFieldType::Email, 'required' => true,
        ]);
        $ticketField = EventFormField::factory()->for($eventForm, 'eventForm')
            ->choice(['VIP', 'Regular'])
            ->create([
                'organization_id' => $organization->id,
                'label' => 'Ticket Type',
                'option_prices' => ['VIP' => 150, 'Regular' => 50],
            ]);

        $eventForm->update(['payment_enabled' => true, 'payment_methods' => ['chip'], 'pricing_field_id' => $ticketField->id]);

        Livewire::test(EventRegistration::class, ['eventForm' => $eventForm])
            ->set("answers.{$emailField->id}", 'ahmad@example.com')
            ->set("answers.{$ticketField->id}", 'VIP')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('step', 'payment')
            ->assertSet('paymentAmountDue', 150.0);
    }

    public function test_the_pricing_field_is_required_when_payment_is_enabled(): void
    {
        $organization = Organization::factory()->create();
        OrganizationPaymentSetting::factory()->for($organization)->chipConfigured()->create();

        $event = Event::factory()->create(['organization_id' => $organization->id]);
        $eventForm = EventForm::factory()->published()->for($event)->create(['organization_id' => $organization->id]);
        $emailField = EventFormField::factory()->for($eventForm, 'eventForm')->create([
            'organization_id' => $organization->id, 'type' => EventFormFieldType::Email, 'required' => true,
        ]);
        $ticketField = EventFormField::factory()->for($eventForm, 'eventForm')
            ->choice(['VIP', 'Regular'])
            ->create([
                'organization_id' => $organization->id,
                'label' => 'Ticket Type',
                'required' => false,
                'option_prices' => ['VIP' => 150, 'Regular' => 50],
            ]);

        $eventForm->update(['payment_enabled' => true, 'payment_methods' => ['chip'], 'pricing_field_id' => $ticketField->id]);

        Livewire::test(EventRegistration::class, ['eventForm' => $eventForm])
            ->set("answers.{$emailField->id}", 'ahmad@example.com')
            ->call('submit')
            ->assertHasErrors(["answers.{$ticketField->id}"]);

        $this->assertSame(0, EventFormResponse::count());
    }

    public function test_a_form_without_payment_enabled_skips_the_payment_step(): void
    {
        $organization = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $organization->id]);
        $eventForm = EventForm::factory()->published()->for($event)->create(['organization_id' => $organization->id]);
        $nameField = EventFormField::factory()->for($eventForm, 'eventForm')->create(['organization_id' => $organization->id]);

        Livewire::test(EventRegistration::class, ['eventForm' => $eventForm])
            ->set("answers.{$nameField->id}", 'Ahmad')
            ->call('submit')
            ->assertSet('step', 'done');

        $this->assertSame(0, EventPayment::count());
    }
}
