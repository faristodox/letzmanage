<?php

namespace App\Livewire\Public;

use App\Enums\EventPaymentMethod;
use App\Enums\EventPaymentStatus;
use App\Models\Event;
use App\Models\EventPayment;
use App\Services\ChipPaymentService;
use App\Services\EventPaymentConfirmationService;
use Livewire\Component;

class EventPaymentReturn extends Component
{
    public int $eventId;

    /**
     * checking never actually renders (resolved synchronously in mount), but
     * exists as the safe default if outcome somehow isn't set.
     */
    public string $outcome = 'checking';

    public function mount(Event $event): void
    {
        $this->eventId = $event->id;

        $responseId = (int) request()->query('response', 0);
        $payment = $responseId ? EventPayment::where('event_form_response_id', $responseId)->first() : null;

        if (! $payment) {
            $this->outcome = 'not_found';

            return;
        }

        if ($payment->status === EventPaymentStatus::Paid) {
            $this->outcome = 'paid';

            return;
        }

        if ($payment->method !== EventPaymentMethod::Chip || ! $payment->chip_purchase_id) {
            // Bank transfer, or a CHIP payment that never made it to CHIP —
            // nothing to re-verify here, just report its current status.
            $this->outcome = 'pending';

            return;
        }

        $setting = $payment->response->eventForm->event->organization?->paymentSetting;

        if (! $setting) {
            $this->outcome = 'pending';

            return;
        }

        $chip = app(ChipPaymentService::class);

        try {
            $purchase = $chip->retrievePurchase($payment->chip_purchase_id, $setting);
        } catch (\Throwable $e) {
            report($e);
            $this->outcome = 'pending';

            return;
        }

        $purchaseStatus = $purchase['status'] ?? null;

        if ($purchaseStatus === 'paid') {
            app(EventPaymentConfirmationService::class)->markPaid($payment->fresh(), $chip->extractFeeAmount($purchase));
            $this->outcome = 'paid';
        } elseif (in_array($purchaseStatus, ['cancelled', 'error', 'expired'], true)) {
            $this->outcome = 'failed';
        } else {
            $this->outcome = 'pending';
        }
    }

    public function render()
    {
        return view('livewire.public.event-payment-return');
    }
}
