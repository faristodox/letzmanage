<?php

namespace App\Livewire\Public;

use App\Enums\EventFormFieldType;
use App\Enums\EventPaymentMethod;
use App\Enums\EventPaymentStatus;
use App\Models\EventForm;
use App\Models\EventFormResponse;
use App\Models\EventPayment;
use App\Models\Organization;
use App\Models\OrganizationPaymentSetting;
use App\Services\ChipPaymentService;
use App\Support\CurrentOrganization;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class EventRegistration extends Component
{
    use WithFileUploads;

    public ?int $organizationId = null;

    public ?int $eventFormId = null;

    public array $answers = [];

    public bool $preview = false;

    /**
     * form: filling in the dynamic fields.
     * payment: response saved, a fee is due, waiting on CHIP/bank transfer.
     * done: fully finished (no payment needed, or payment step completed).
     */
    public string $step = 'form';

    public ?int $responseId = null;

    public ?float $paymentAmountDue = null;

    public $paymentReceipt = null;

    public ?string $paymentSubmitError = null;

    /**
     * Runs on every Livewire request (initial + updates). Re-establishes the
     * organization (tenant) context so all queries stay scoped to the org whose
     * public page this is — the slug isn't present on update requests.
     */
    public function boot(): void
    {
        if ($this->organizationId) {
            app(CurrentOrganization::class)->set(Organization::find($this->organizationId));
        }
    }

    public function mount(EventForm $eventForm, bool $preview = false): void
    {
        $this->organizationId = app(CurrentOrganization::class)->id();
        $this->eventFormId = $eventForm->id;
        $this->preview = $preview;
    }

    private function eventForm(): EventForm
    {
        return EventForm::with(['fields', 'event.organization.paymentSetting'])->findOrFail($this->eventFormId);
    }

    private function rulesFor(EventForm $eventForm): array
    {
        $rules = [];

        foreach ($eventForm->fields as $field) {
            $key = "answers.{$field->id}";

            // The pricing field must be answered whenever payment is enabled —
            // the amount owed can't be resolved otherwise — regardless of
            // whether the admin also marked it required for its own sake.
            $required = $field->required
                || ($eventForm->payment_enabled && $eventForm->pricing_field_id === $field->id);
            $prefix = $required ? 'required' : 'nullable';

            $rules[$key] = match ($field->type) {
                EventFormFieldType::Email => [$prefix, 'email'],
                EventFormFieldType::Number => [$prefix, 'numeric'],
                EventFormFieldType::Date => [$prefix, 'date'],
                EventFormFieldType::Checkbox => [$required ? 'required' : 'nullable', 'array'],
                EventFormFieldType::Select, EventFormFieldType::Radio => [$prefix, 'string', Rule::in($field->options ?? [])],
                default => [$prefix, 'string', 'max:2000'],
            };

            if ($field->type === EventFormFieldType::Checkbox) {
                $rules["{$key}.*"] = [Rule::in($field->options ?? [])];
            }
        }

        return $rules;
    }

    /**
     * Methods this specific submission can actually pay with: the form must
     * offer it AND the organization must still have it configured — a form
     * can be misconfigured or an org can disable a method after the form was
     * set up.
     *
     * @return array<int, string>
     */
    private function resolveAvailableMethods(EventForm $eventForm, ?OrganizationPaymentSetting $setting): array
    {
        $formMethods = $eventForm->payment_methods ?? [];
        $methods = [];

        if (in_array(EventPaymentMethod::Chip->value, $formMethods, true)
            && $setting?->hasChipConfigured()
            && $this->resolveClientEmail($eventForm) !== null) {
            $methods[] = EventPaymentMethod::Chip->value;
        }

        if (in_array(EventPaymentMethod::BankTransfer->value, $formMethods, true) && $setting?->hasBankTransferConfigured()) {
            $methods[] = EventPaymentMethod::BankTransfer->value;
        }

        return $methods;
    }

    /**
     * CHIP requires a client email — pulled from this form's own Email-type
     * field (if it has one) rather than asked for separately.
     */
    private function resolveClientEmail(EventForm $eventForm): ?string
    {
        $emailField = $eventForm->fields->firstWhere('type', EventFormFieldType::Email);

        if (! $emailField) {
            return null;
        }

        return $this->answers[$emailField->id] ?? null;
    }

    public function submit(): void
    {
        $eventForm = $this->eventForm();

        if (! $this->preview && ! $eventForm->isAcceptingResponses()) {
            return;
        }

        $this->validate($this->rulesFor($eventForm));

        // Preview runs the same validation a real visitor would hit, but never
        // writes a response (or a payment) — it's for the form owner to
        // sanity-check the form.
        if ($this->preview) {
            $this->step = 'done';

            return;
        }

        $response = EventFormResponse::create([
            'event_form_id' => $eventForm->id,
            'answers' => $this->answers,
            'submitted_ip' => request()->ip(),
        ]);

        // Lets an embedding parent (e.g. the event-day check-in flow, when
        // on-site registration is enabled) react without this component
        // needing to know anything about check-in.
        $this->dispatch('event-form-submitted', responseId: $response->id);

        $amountDue = $eventForm->resolveAmountFor($this->answers);
        $setting = $eventForm->event->organization?->paymentSetting;
        $availableMethods = $eventForm->payment_enabled ? $this->resolveAvailableMethods($eventForm, $setting) : [];

        if (! $eventForm->payment_enabled || $amountDue === null || $amountDue <= 0 || $availableMethods === []) {
            $this->step = 'done';

            return;
        }

        $this->responseId = $response->id;
        $this->paymentAmountDue = $amountDue;
        $this->step = 'payment';
    }

    public function payWithChip(ChipPaymentService $chip): void
    {
        if ($this->step !== 'payment' || ! $this->responseId) {
            return;
        }

        $this->paymentSubmitError = null;

        $eventForm = $this->eventForm();
        $setting = $eventForm->event->organization?->paymentSetting;
        $email = $this->resolveClientEmail($eventForm);

        if (! $setting || ! $setting->hasChipConfigured() || ! $email) {
            $this->paymentSubmitError = __('CHIP payment is not available right now.');

            return;
        }

        $response = EventFormResponse::findOrFail($this->responseId);

        $productName = $eventForm->pricing_field_id
            ? trim(($eventForm->pricingField->label ?? '').': '.($this->answers[$eventForm->pricing_field_id] ?? ''))
            : $eventForm->event->title.' — '.__('Registration Fee');

        $returnUrl = route('event-registration.payment-return', [
            'organization' => $eventForm->event->organization,
            'eventSlug' => $eventForm->event->slug,
        ]).'?response='.$response->id;

        try {
            $checkout = $chip->createCheckout(
                response: $response,
                amount: $this->paymentAmountDue,
                setting: $setting,
                email: $email,
                productName: $productName,
                successUrl: $returnUrl,
                failureUrl: $returnUrl,
                cancelUrl: $returnUrl,
                successCallbackUrl: route('chip.webhook'),
            );
        } catch (\Throwable $e) {
            report($e);
            $this->paymentSubmitError = __('Could not start payment. Please try again.');

            return;
        }

        EventPayment::updateOrCreate(
            ['event_form_response_id' => $response->id],
            [
                'method' => EventPaymentMethod::Chip,
                'amount' => $this->paymentAmountDue,
                'currency' => 'MYR',
                'status' => EventPaymentStatus::Pending,
                'chip_purchase_id' => $checkout['id'],
            ]
        );

        $this->redirect($checkout['checkout_url']);
    }

    public function submitBankTransfer(): void
    {
        if ($this->step !== 'payment' || ! $this->responseId) {
            return;
        }

        $this->validate([
            'paymentReceipt' => ['required', 'image', 'max:4096'],
        ]);

        $path = $this->paymentReceipt->store('payment-receipts', 'public');

        EventPayment::updateOrCreate(
            ['event_form_response_id' => $this->responseId],
            [
                'method' => EventPaymentMethod::BankTransfer,
                'amount' => $this->paymentAmountDue,
                'currency' => 'MYR',
                'status' => EventPaymentStatus::Pending,
                'receipt_path' => $path,
            ]
        );

        $this->step = 'done';
    }

    public function render()
    {
        $eventForm = $this->eventForm();
        $setting = $eventForm->event->organization?->paymentSetting;

        return view('livewire.public.event-registration', [
            'eventForm' => $eventForm,
            'event' => $eventForm->event,
            'availablePaymentMethods' => $this->step === 'payment' ? $this->resolveAvailableMethods($eventForm, $setting) : [],
            'bankName' => $setting?->bank_name,
            'bankAccountNumber' => $setting?->bank_account_number,
            'bankAccountHolder' => $setting?->bank_account_holder,
        ]);
    }
}
