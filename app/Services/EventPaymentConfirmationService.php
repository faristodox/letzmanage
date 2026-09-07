<?php

namespace App\Services;

use App\Enums\EventPaymentMethod;
use App\Enums\EventPaymentStatus;
use App\Enums\EventTransactionSource;
use App\Enums\EventTransactionType;
use App\Models\EventPayment;
use Illuminate\Support\Facades\DB;

/**
 * Confirms a payment as paid and creates its matching ledger entry — shared
 * by both the payment-return page (Phase C, checked when the registrant
 * comes back from CHIP) and the CHIP webhook (Phase D), so there is exactly
 * one place that decides "this payment is now paid."
 *
 * Idempotent: calling this twice for an already-paid payment is a no-op,
 * since CHIP retries callbacks and the return page and webhook can both
 * race to confirm the same payment.
 *
 * Callers are responsible for setting CurrentOrganization to the payment's
 * organization before calling this, matching the pattern used everywhere
 * else in this codebase — this service does not do it itself.
 */
class EventPaymentConfirmationService
{
    /**
     * @param  ?float  $gatewayFeeAmount  CHIP's transaction fee for this payment
     *                                    (from ChipPaymentService::extractFeeAmount()),
     *                                    if the caller has it. Never applies to
     *                                    bank transfer — there's no gateway fee.
     */
    public function markPaid(EventPayment $payment, ?float $gatewayFeeAmount = null): void
    {
        if ($payment->status === EventPaymentStatus::Paid) {
            return;
        }

        DB::transaction(function () use ($payment, $gatewayFeeAmount) {
            $response = $payment->response;
            $event = $response->eventForm->event;
            $isChip = $payment->method === EventPaymentMethod::Chip;

            $transaction = $event->transactions()->create([
                'event_form_response_id' => $response->id,
                'type' => EventTransactionType::Income,
                'category' => 'Registration Fee',
                'amount' => $payment->amount,
                'gateway_fee_amount' => $isChip ? $gatewayFeeAmount : null,
                'transaction_date' => now()->toDateString(),
                'source' => $isChip
                    ? EventTransactionSource::Gateway
                    : EventTransactionSource::BankTransfer,
            ]);

            $payment->update([
                'status' => EventPaymentStatus::Paid,
                'paid_at' => now(),
                'event_transaction_id' => $transaction->id,
            ]);
        });
    }
}
