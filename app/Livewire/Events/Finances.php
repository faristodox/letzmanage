<?php

namespace App\Livewire\Events;

use App\Enums\EventTransactionType;
use App\Models\Event;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Finances extends Component
{
    public Event $event;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $type = '';

    public string $category = '';

    public string $amount = '';

    public string $transactionDate = '';

    public string $description = '';

    public ?int $responseId = null;

    public ?int $confirmingDeleteId = null;

    public function mount(Event $event): void
    {
        $this->authorize('viewFinances', $event);

        $this->event = $event;
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'category', 'amount', 'description', 'responseId']);
        $this->transactionDate = now()->format('Y-m-d');
    }

    public function recordIncome(): void
    {
        $this->authorize('manageFinances', $this->event);

        $this->resetForm();
        $this->type = EventTransactionType::Income->value;
        $this->showModal = true;
    }

    public function recordExpense(): void
    {
        $this->authorize('manageFinances', $this->event);

        $this->resetForm();
        $this->type = EventTransactionType::Expense->value;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('manageFinances', $this->event);

        $transaction = $this->event->transactions()->findOrFail($id);

        $this->editingId = $transaction->id;
        $this->type = $transaction->type->value;
        $this->category = $transaction->category;
        $this->amount = (string) $transaction->amount;
        $this->transactionDate = $transaction->transaction_date->format('Y-m-d');
        $this->description = (string) $transaction->description;
        $this->responseId = $transaction->event_form_response_id;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authorize('manageFinances', $this->event);

        $registrationForm = $this->event->registrationForm;
        $responseIds = $this->type === EventTransactionType::Income->value && $registrationForm
            ? $registrationForm->responses()->pluck('id')->all()
            : [];

        $data = $this->validate([
            'type' => ['required', Rule::in(array_column(EventTransactionType::cases(), 'value'))],
            'category' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transactionDate' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'responseId' => ['nullable', Rule::in($responseIds)],
        ]);

        $payload = [
            'type' => EventTransactionType::from($data['type']),
            'category' => $data['category'],
            'amount' => $data['amount'],
            'transaction_date' => $data['transactionDate'],
            'description' => $data['description'] ?: null,
            'event_form_response_id' => $data['responseId'] ?: null,
        ];

        if ($this->editingId) {
            $this->event->transactions()->findOrFail($this->editingId)->update($payload);
        } else {
            $this->event->transactions()->create([
                ...$payload,
                'recorded_by' => auth()->id(),
            ]);
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('manageFinances', $this->event);

        $this->confirmingDeleteId = $id;
    }

    public function closeDeleteModal(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(): void
    {
        $this->authorize('manageFinances', $this->event);

        $this->event->transactions()->findOrFail($this->confirmingDeleteId)->delete();
        $this->confirmingDeleteId = null;
    }

    public function render()
    {
        $registrationForm = $this->event->registrationForm;

        return view('livewire.events.finances', [
            'transactions' => $this->event->transactions()->with('response', 'recordedBy')->get(),
            'registrationResponses' => $this->type === EventTransactionType::Income->value && $registrationForm
                ? $registrationForm->responses()->get()
                : collect(),
            'totalIncome' => $this->event->totalIncome(),
            'totalExpenses' => $this->event->totalExpenses(),
            'netBalance' => $this->event->netBalance(),
        ]);
    }
}
