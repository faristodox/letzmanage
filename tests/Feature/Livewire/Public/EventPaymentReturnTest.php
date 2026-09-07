<?php

namespace Tests\Feature\Livewire\Public;

use App\Enums\EventPaymentStatus;
use App\Enums\EventTransactionSource;
use App\Models\Event;
use App\Models\EventForm;
use App\Models\EventFormResponse;
use App\Models\EventPayment;
use App\Models\EventTransaction;
use App\Models\Organization;
use App\Models\OrganizationPaymentSetting;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EventPaymentReturnTest extends TestCase
{
    use RefreshDatabase;

    private function setupPayment(Organization $organization, array $paymentOverrides = []): EventPayment
    {
        return app(CurrentOrganization::class)->runFor($organization, function () use ($organization, $paymentOverrides) {
            OrganizationPaymentSetting::factory()->for($organization)->chipConfigured()->create();
            $event = Event::factory()->create(['slug' => 'family-day']);
            $eventForm = EventForm::factory()->published()->for($event)->create();
            $response = EventFormResponse::factory()->for($eventForm, 'eventForm')->create();

            return EventPayment::factory()->for($response, 'response')->create([
                'chip_purchase_id' => 'chip-purchase-1',
                'amount' => 50,
                ...$paymentOverrides,
            ]);
        });
    }

    private function returnUrl(Organization $organization, EventPayment $payment): string
    {
        return route('event-registration.payment-return', [
            'organization' => $organization,
            'eventSlug' => $payment->response->eventForm->event->slug,
        ]).'?response='.$payment->event_form_response_id;
    }

    public function test_an_already_paid_payment_shows_paid_without_calling_chip(): void
    {
        Http::fake();

        $organization = Organization::factory()->create();
        $payment = $this->setupPayment($organization, ['status' => EventPaymentStatus::Paid, 'paid_at' => now()]);

        $this->get($this->returnUrl($organization, $payment))
            ->assertOk()
            ->assertSee('Payment received');

        Http::assertNothingSent();
    }

    public function test_chip_confirms_paid_and_creates_the_ledger_entry(): void
    {
        Http::fake([
            'https://gate.chip-in.asia/api/v1/purchases/chip-purchase-1/' => Http::response([
                'status' => 'paid',
                'payment' => ['fee_amount' => 150],
            ]),
        ]);

        $organization = Organization::factory()->create();
        $payment = $this->setupPayment($organization);

        $this->get($this->returnUrl($organization, $payment))
            ->assertOk()
            ->assertSee('Payment received');

        $payment->refresh();
        $this->assertSame(EventPaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->event_transaction_id);

        $transaction = EventTransaction::find($payment->event_transaction_id);
        $this->assertSame(EventTransactionSource::Gateway, $transaction->source);
        $this->assertSame('50.00', (string) $transaction->amount);
        $this->assertSame('1.50', (string) $transaction->gateway_fee_amount);
    }

    public function test_visiting_twice_does_not_create_a_duplicate_ledger_entry(): void
    {
        Http::fake([
            'https://gate.chip-in.asia/api/v1/purchases/chip-purchase-1/' => Http::response(['status' => 'paid']),
        ]);

        $organization = Organization::factory()->create();
        $payment = $this->setupPayment($organization);
        $url = $this->returnUrl($organization, $payment);

        $this->get($url)->assertOk();
        $this->get($url)->assertOk();

        $this->assertSame(1, EventTransaction::count());
    }

    public function test_a_still_processing_purchase_shows_pending(): void
    {
        Http::fake([
            'https://gate.chip-in.asia/api/v1/purchases/chip-purchase-1/' => Http::response(['status' => 'sent']),
        ]);

        $organization = Organization::factory()->create();
        $payment = $this->setupPayment($organization);

        $this->get($this->returnUrl($organization, $payment))
            ->assertOk()
            ->assertSee('pending confirmation');

        $this->assertSame(EventPaymentStatus::Pending, $payment->refresh()->status);
    }

    public function test_a_cancelled_purchase_shows_failed(): void
    {
        Http::fake([
            'https://gate.chip-in.asia/api/v1/purchases/chip-purchase-1/' => Http::response(['status' => 'cancelled']),
        ]);

        $organization = Organization::factory()->create();
        $payment = $this->setupPayment($organization);

        $this->get($this->returnUrl($organization, $payment))
            ->assertOk()
            ->assertSee('Payment not completed');
    }

    public function test_an_unknown_response_id_shows_not_found(): void
    {
        $organization = Organization::factory()->create();
        $event = app(CurrentOrganization::class)->runFor($organization, function () {
            $event = Event::factory()->create();
            EventForm::factory()->published()->for($event)->create();

            return $event;
        });

        $this->get(route('event-registration.payment-return', [
            'organization' => $organization,
            'eventSlug' => $event->slug,
        ]).'?response=999999')
            ->assertOk()
            ->assertSee('Payment not found');
    }

    public function test_a_payment_cannot_be_confirmed_through_a_different_organizations_url(): void
    {
        $orgA = Organization::factory()->create(['slug' => 'org-a']);
        $orgB = Organization::factory()->create(['slug' => 'org-b']);

        $paymentA = $this->setupPayment($orgA);

        $eventB = app(CurrentOrganization::class)->runFor($orgB, function () {
            $event = Event::factory()->create(['slug' => 'family-day']);
            EventForm::factory()->published()->for($event)->create();

            return $event;
        });

        // Org A's response id used against Org B's URL must not resolve.
        $this->get(route('event-registration.payment-return', [
            'organization' => $orgB,
            'eventSlug' => $eventB->slug,
        ]).'?response='.$paymentA->event_form_response_id)
            ->assertOk()
            ->assertSee('Payment not found');
    }
}
