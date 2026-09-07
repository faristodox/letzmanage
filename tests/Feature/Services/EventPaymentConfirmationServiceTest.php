<?php

namespace Tests\Feature\Services;

use App\Enums\EventPaymentMethod;
use App\Enums\EventPaymentStatus;
use App\Enums\EventTransactionSource;
use App\Models\Event;
use App\Models\EventForm;
use App\Models\EventFormResponse;
use App\Models\EventPayment;
use App\Models\EventTransaction;
use App\Models\Organization;
use App\Services\EventPaymentConfirmationService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventPaymentConfirmationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirming_a_bank_transfer_payment_uses_the_bank_transfer_source(): void
    {
        $organization = Organization::factory()->create();

        $payment = app(CurrentOrganization::class)->runFor($organization, function () {
            $event = Event::factory()->create();
            $eventForm = EventForm::factory()->published()->for($event)->create();
            $response = EventFormResponse::factory()->for($eventForm, 'eventForm')->create();

            return EventPayment::factory()->for($response, 'response')->bankTransfer()->create(['amount' => 75]);
        });

        app(CurrentOrganization::class)->set($organization);
        app(EventPaymentConfirmationService::class)->markPaid($payment);

        $payment->refresh();
        $this->assertSame(EventPaymentStatus::Paid, $payment->status);

        $transaction = EventTransaction::find($payment->event_transaction_id);
        $this->assertSame(EventTransactionSource::BankTransfer, $transaction->source);
        $this->assertSame('75.00', (string) $transaction->amount);
    }

    public function test_confirming_a_chip_payment_with_a_fee_records_the_gross_amount_and_the_fee(): void
    {
        $organization = Organization::factory()->create();

        $payment = app(CurrentOrganization::class)->runFor($organization, function () {
            $event = Event::factory()->create();
            $eventForm = EventForm::factory()->published()->for($event)->create();
            $response = EventFormResponse::factory()->for($eventForm, 'eventForm')->create();

            return EventPayment::factory()->for($response, 'response')->create([
                'method' => EventPaymentMethod::Chip,
                'amount' => 50,
            ]);
        });

        app(CurrentOrganization::class)->set($organization);
        app(EventPaymentConfirmationService::class)->markPaid($payment, 1.5);

        $transaction = EventTransaction::find($payment->fresh()->event_transaction_id);
        $this->assertSame(EventTransactionSource::Gateway, $transaction->source);
        $this->assertSame('50.00', (string) $transaction->amount);
        $this->assertSame('1.50', (string) $transaction->gateway_fee_amount);
        $this->assertSame(48.5, $transaction->netAmount());

        $event = $transaction->event;
        $this->assertSame(50.0, $event->totalIncome());
        $this->assertSame(1.5, $event->totalGatewayFees());
        $this->assertSame(48.5, $event->netBalance());
    }

    public function test_a_bank_transfer_payment_never_records_a_gateway_fee_even_if_one_is_passed(): void
    {
        $organization = Organization::factory()->create();

        $payment = app(CurrentOrganization::class)->runFor($organization, function () {
            $event = Event::factory()->create();
            $eventForm = EventForm::factory()->published()->for($event)->create();
            $response = EventFormResponse::factory()->for($eventForm, 'eventForm')->create();

            return EventPayment::factory()->for($response, 'response')->bankTransfer()->create(['amount' => 75]);
        });

        app(CurrentOrganization::class)->set($organization);
        app(EventPaymentConfirmationService::class)->markPaid($payment, 2.0);

        $transaction = EventTransaction::find($payment->fresh()->event_transaction_id);
        $this->assertNull($transaction->gateway_fee_amount);
        $this->assertNull($transaction->netAmount());
    }

    public function test_confirming_an_already_paid_payment_is_a_no_op(): void
    {
        $organization = Organization::factory()->create();

        $payment = app(CurrentOrganization::class)->runFor($organization, function () {
            $event = Event::factory()->create();
            $eventForm = EventForm::factory()->published()->for($event)->create();
            $response = EventFormResponse::factory()->for($eventForm, 'eventForm')->create();

            return EventPayment::factory()->for($response, 'response')->paid()->create(['amount' => 40]);
        });

        app(CurrentOrganization::class)->set($organization);
        app(EventPaymentConfirmationService::class)->markPaid($payment);

        $this->assertSame(0, EventTransaction::count());
    }
}
