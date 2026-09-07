<?php

namespace App\Livewire\EventForms;

use App\Enums\EventPaymentStatus;
use App\Models\EventForm;
use App\Models\EventPayment;
use App\Services\EventPaymentConfirmationService;
use Livewire\Component;

class Payments extends Component
{
    public EventForm $eventForm;

    public ?int $confirmingRejectId = null;

    public function mount(EventForm $eventForm): void
    {
        $this->authorize('reviewPayments', $eventForm);

        $this->eventForm = $eventForm;
    }

    private function paymentsQuery()
    {
        return EventPayment::whereHas('response', fn ($query) => $query->where('event_form_id', $this->eventForm->id));
    }

    public function approve(int $paymentId, EventPaymentConfirmationService $confirmation): void
    {
        $this->authorize('reviewPayments', $this->eventForm);

        $payment = $this->paymentsQuery()->findOrFail($paymentId);

        $confirmation->markPaid($payment);
    }

    public function confirmReject(int $paymentId): void
    {
        $this->authorize('reviewPayments', $this->eventForm);

        $this->confirmingRejectId = $paymentId;
    }

    public function closeRejectModal(): void
    {
        $this->confirmingRejectId = null;
    }

    public function reject(): void
    {
        $this->authorize('reviewPayments', $this->eventForm);

        $payment = $this->paymentsQuery()->findOrFail($this->confirmingRejectId);

        $payment->update([
            'status' => EventPaymentStatus::Rejected,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        $this->confirmingRejectId = null;
    }

    public function render()
    {
        $payments = $this->paymentsQuery()
            ->with(['response', 'reviewedBy'])
            ->latest()
            ->get();

        return view('livewire.event-forms.payments', [
            'payments' => $payments,
        ]);
    }
}
