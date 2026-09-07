<?php

namespace App\Livewire\EventForms;

use App\Enums\CheckInVerificationMode;
use App\Enums\EventFormFieldType;
use App\Enums\EventFormStatus;
use App\Enums\EventFormType;
use App\Enums\EventPaymentMethod;
use App\Models\EventForm;
use App\Models\EventFormField;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class Builder extends Component
{
    use WithFileUploads;

    public EventForm $eventForm;

    public string $eventTitle = '';

    public string $eventDescription = '';

    public $banner = null;

    public ?string $existingBannerPath = null;

    public bool $removeBanner = false;

    public string $status = '';

    public string $closes_at = '';

    public bool $showFieldModal = false;

    public ?int $editingFieldId = null;

    public string $fieldLabel = '';

    public string $fieldType = '';

    public string $fieldHelpText = '';

    public bool $fieldRequired = false;

    public string $fieldOptions = '';

    public ?int $confirmingDeleteFieldId = null;

    public bool $checkinEnabled = false;

    public string $checkinStartsAt = '';

    public string $checkinEndsAt = '';

    public bool $checkinLinkEnabled = true;

    public bool $checkinQrEnabled = true;

    public bool $checkinManualEnabled = true;

    public array $checkinVerificationFieldIds = [];

    public string $checkinVerificationMode = 'all';

    public bool $checkinOnsiteRegistrationEnabled = false;

    public bool $confirmingDeleteFeedbackForm = false;

    public bool $paymentEnabled = false;

    /** @var array<int, string> */
    public array $paymentMethods = [];

    public string $paymentPricingModel = 'flat';

    public string $paymentAmount = '';

    public ?int $paymentPricingFieldId = null;

    /** @var array<string, string> */
    public array $paymentOptionPrices = [];

    public function mount(EventForm $eventForm): void
    {
        $this->authorize('update', $eventForm);

        $this->eventForm = $eventForm;

        $event = $eventForm->event;
        $this->eventTitle = $event->title;
        $this->eventDescription = (string) $event->description;
        $this->existingBannerPath = $event->banner_path;

        $this->status = $eventForm->status->value;
        $this->closes_at = $eventForm->closes_at?->format('Y-m-d\TH:i') ?? '';

        $this->checkinEnabled = (bool) $eventForm->checkin_enabled;
        $this->checkinStartsAt = $eventForm->checkin_starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->checkinEndsAt = $eventForm->checkin_ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->checkinLinkEnabled = $eventForm->checkin_link_enabled ?? true;
        $this->checkinQrEnabled = $eventForm->checkin_qr_enabled ?? true;
        $this->checkinManualEnabled = $eventForm->checkin_manual_enabled ?? true;
        $this->checkinVerificationFieldIds = $eventForm->checkin_verification_field_ids ?? [];
        $this->checkinVerificationMode = $eventForm->checkin_verification_mode?->value ?? CheckInVerificationMode::All->value;
        $this->checkinOnsiteRegistrationEnabled = (bool) $eventForm->checkin_onsite_registration_enabled;

        $this->paymentEnabled = (bool) $eventForm->payment_enabled;
        $this->paymentMethods = $eventForm->payment_methods ?? [];
        $this->paymentAmount = $eventForm->payment_amount !== null ? (string) $eventForm->payment_amount : '';
        $this->paymentPricingFieldId = $eventForm->pricing_field_id;
        $this->paymentPricingModel = $eventForm->pricing_field_id ? 'tiered' : 'flat';
        $this->syncPaymentOptionPrices($eventForm->pricing_field_id ? $eventForm->pricingField : null);
    }

    public function saveEventSettings(): void
    {
        $this->authorize('update', $this->eventForm);

        $data = $this->validate([
            'eventTitle' => ['required', 'string', 'max:255'],
            'eventDescription' => ['nullable', 'string'],
            'banner' => ['nullable', 'image', 'max:2048'],
        ]);

        $event = $this->eventForm->event;
        $bannerPath = $event->banner_path;

        if ($this->banner) {
            if ($bannerPath) {
                Storage::disk('public')->delete($bannerPath);
            }
            $bannerPath = $this->banner->store('events', 'public');
        } elseif ($this->removeBanner && $bannerPath) {
            Storage::disk('public')->delete($bannerPath);
            $bannerPath = null;
        }

        $event->update([
            'title' => $data['eventTitle'],
            'description' => $data['eventDescription'] ?: null,
            'banner_path' => $bannerPath,
        ]);

        $this->banner = null;
        $this->removeBanner = false;
        $this->existingBannerPath = $bannerPath;

        session()->flash('status', __('Event settings saved.'));
    }

    /**
     * Fields eligible as check-in verification fields: checkbox (multi-select)
     * fields store arrays and can never match a single submitted value.
     */
    public function checkinEligibleFields()
    {
        return $this->eventForm->fields()->get()->reject(fn ($field) => $field->type === EventFormFieldType::Checkbox);
    }

    public function saveCheckinSettings(): void
    {
        $this->authorize('update', $this->eventForm);

        $eligibleFieldIds = $this->checkinEligibleFields()->pluck('id')->all();

        $data = $this->validate([
            'checkinEnabled' => ['boolean'],
            'checkinStartsAt' => ['nullable', 'date'],
            'checkinEndsAt' => ['nullable', 'date'],
            'checkinLinkEnabled' => ['boolean'],
            'checkinQrEnabled' => ['boolean'],
            'checkinManualEnabled' => ['boolean'],
            'checkinVerificationFieldIds' => [
                $this->checkinEnabled ? 'required' : 'nullable', 'array',
            ],
            'checkinVerificationFieldIds.*' => [Rule::in($eligibleFieldIds)],
            'checkinVerificationMode' => ['required', Rule::in(array_column(CheckInVerificationMode::cases(), 'value'))],
            'checkinOnsiteRegistrationEnabled' => ['boolean'],
        ], [
            'checkinVerificationFieldIds.required' => __('Select at least one verification field to enable check-in.'),
        ]);

        $this->eventForm->update([
            'checkin_enabled' => $data['checkinEnabled'],
            'checkin_starts_at' => $data['checkinStartsAt'] ?: null,
            'checkin_ends_at' => $data['checkinEndsAt'] ?: null,
            'checkin_link_enabled' => $data['checkinLinkEnabled'],
            'checkin_qr_enabled' => $data['checkinQrEnabled'],
            'checkin_manual_enabled' => $data['checkinManualEnabled'],
            'checkin_verification_field_ids' => $data['checkinVerificationFieldIds'] ?: [],
            'checkin_verification_mode' => CheckInVerificationMode::from($data['checkinVerificationMode']),
            'checkin_onsite_registration_enabled' => $data['checkinOnsiteRegistrationEnabled'],
        ]);

        session()->flash('status', __('Check-in settings saved.'));
    }

    public function hasResponses(): bool
    {
        return $this->eventForm->responses()->exists();
    }

    /**
     * Fields eligible to price the form by: single-choice only (select/radio)
     * — a checkbox field can select multiple options at once, which doesn't
     * resolve to one price without a summing model we don't support yet.
     */
    public function pricingEligibleFields()
    {
        return $this->eventForm->fields()->whereIn('type', [EventFormFieldType::Select, EventFormFieldType::Radio])->get();
    }

    /**
     * Payment methods the organization has actually connected in Payment
     * Settings — a form can't offer a method the org hasn't configured.
     */
    public function availablePaymentMethods(): array
    {
        $setting = $this->eventForm->event->organization?->paymentSetting;

        $methods = [];

        if ($setting?->hasChipConfigured()) {
            $methods[] = EventPaymentMethod::Chip->value;
        }

        if ($setting?->hasBankTransferConfigured()) {
            $methods[] = EventPaymentMethod::BankTransfer->value;
        }

        return $methods;
    }

    private function syncPaymentOptionPrices(?EventFormField $field): void
    {
        $this->paymentOptionPrices = [];

        if (! $field) {
            return;
        }

        $existing = $field->option_prices ?? [];

        foreach ($field->options ?? [] as $option) {
            $this->paymentOptionPrices[$option] = isset($existing[$option]) ? (string) $existing[$option] : '';
        }
    }

    public function updatedPaymentPricingFieldId($value): void
    {
        $field = $value ? $this->eventForm->fields()->find($value) : null;
        $this->syncPaymentOptionPrices($field);
    }

    public function savePaymentSettings(): void
    {
        $this->authorize('update', $this->eventForm);

        $availableMethods = $this->availablePaymentMethods();

        $data = $this->validate([
            'paymentEnabled' => ['boolean'],
            'paymentMethods' => [$this->paymentEnabled ? 'required' : 'nullable', 'array'],
            'paymentMethods.*' => [Rule::in($availableMethods)],
            'paymentPricingModel' => ['required', 'in:flat,tiered'],
            'paymentAmount' => [
                $this->paymentEnabled && $this->paymentPricingModel === 'flat' ? 'required' : 'nullable',
                'numeric', 'min:0.01',
            ],
            'paymentPricingFieldId' => [
                $this->paymentEnabled && $this->paymentPricingModel === 'tiered' ? 'required' : 'nullable',
                Rule::in($this->pricingEligibleFields()->pluck('id')->all()),
            ],
        ], [
            'paymentMethods.required' => __('Select at least one payment method.'),
            'paymentPricingFieldId.required' => __('Choose which field determines the price.'),
        ]);

        $pricingFieldId = $this->paymentPricingModel === 'tiered' ? $data['paymentPricingFieldId'] : null;
        $pricingField = $pricingFieldId ? $this->eventForm->fields()->find($pricingFieldId) : null;

        if ($this->paymentEnabled && $pricingField) {
            foreach ($pricingField->options ?? [] as $option) {
                $price = $this->paymentOptionPrices[$option] ?? null;

                if ($price === null || $price === '' || (float) $price <= 0) {
                    $this->addError('paymentOptionPrices.'.$option, __('Enter a price for every option.'));

                    return;
                }
            }
        }

        $this->eventForm->update([
            'payment_enabled' => $data['paymentEnabled'],
            'payment_methods' => $data['paymentMethods'] ?? [],
            'payment_amount' => $this->paymentPricingModel === 'flat' ? $data['paymentAmount'] : null,
            'pricing_field_id' => $pricingFieldId,
        ]);

        if ($pricingField) {
            $prices = [];

            foreach ($pricingField->options ?? [] as $option) {
                $prices[$option] = (float) $this->paymentOptionPrices[$option];
            }

            $pricingField->update(['option_prices' => $prices]);
        }

        session()->flash('status', __('Payment settings saved.'));
    }

    public function saveFormSettings(): void
    {
        $this->authorize('update', $this->eventForm);

        $data = $this->validate([
            'status' => ['required', 'string'],
            'closes_at' => ['nullable', 'date'],
        ]);

        $this->eventForm->update([
            'status' => EventFormStatus::from($data['status']),
            'closes_at' => $data['closes_at'] ?: null,
        ]);

        session()->flash('status', __('Form settings saved.'));
    }

    /**
     * Feedback surveys reuse the exact same form/field/response engine as
     * registration — they're just another EventForm, of type Feedback,
     * linked to the same Event. One per event for now.
     */
    public function addFeedbackForm(): void
    {
        $this->authorize('update', $this->eventForm);

        if ($this->eventForm->type !== EventFormType::Registration || $this->eventForm->event->feedbackForm) {
            return;
        }

        $feedbackForm = EventForm::create([
            'event_id' => $this->eventForm->event_id,
            'type' => EventFormType::Feedback,
            'status' => EventFormStatus::Draft,
            'created_by' => auth()->id(),
        ]);

        $this->redirect(route('event-forms.builder', $feedbackForm), navigate: true);
    }

    public function confirmDeleteFeedbackForm(): void
    {
        $feedbackForm = $this->eventForm->event->feedbackForm;

        if (! $feedbackForm) {
            return;
        }

        $this->authorize('delete', $feedbackForm);

        $this->confirmingDeleteFeedbackForm = true;
    }

    public function closeDeleteFeedbackModal(): void
    {
        $this->confirmingDeleteFeedbackForm = false;
    }

    public function deleteFeedbackForm(): void
    {
        $feedbackForm = $this->eventForm->event->feedbackForm;

        if (! $feedbackForm) {
            return;
        }

        $this->authorize('delete', $feedbackForm);

        $feedbackForm->delete();
        $this->confirmingDeleteFeedbackForm = false;
    }

    public function addField(): void
    {
        $this->authorize('update', $this->eventForm);

        $this->resetFieldForm();
        $this->fieldType = EventFormFieldType::Text->value;
        $this->showFieldModal = true;
    }

    public function editField(int $id): void
    {
        $this->authorize('update', $this->eventForm);

        $field = $this->eventForm->fields()->findOrFail($id);

        $this->editingFieldId = $field->id;
        $this->fieldLabel = $field->label;
        $this->fieldType = $field->type->value;
        $this->fieldHelpText = (string) $field->help_text;
        $this->fieldRequired = $field->required;
        $this->fieldOptions = implode("\n", $field->options ?? []);
        $this->showFieldModal = true;
    }

    public function closeFieldModal(): void
    {
        $this->showFieldModal = false;
        $this->resetFieldForm();
        $this->resetValidation();
    }

    private function resetFieldForm(): void
    {
        $this->reset(['editingFieldId', 'fieldLabel', 'fieldType', 'fieldHelpText', 'fieldRequired', 'fieldOptions']);
    }

    public function saveField(): void
    {
        $this->authorize('update', $this->eventForm);

        $data = $this->validate([
            'fieldLabel' => ['required', 'string', 'max:255'],
            'fieldType' => ['required', 'string'],
            'fieldHelpText' => ['nullable', 'string'],
            'fieldOptions' => ['nullable', 'string'],
        ]);
        $type = EventFormFieldType::from($data['fieldType']);

        $options = $type->isChoice()
            ? collect(preg_split('/\r\n|\r|\n/', $data['fieldOptions']))
                ->map(fn ($line) => trim($line))
                ->filter()
                ->values()
                ->all()
            : null;

        if ($this->editingFieldId) {
            $field = $this->eventForm->fields()->findOrFail($this->editingFieldId);

            // Once responses exist, the field's type/options are locked so stored
            // answers stay consistent — only label/help/required may still change.
            if ($this->hasResponses()) {
                $field->update([
                    'label' => $data['fieldLabel'],
                    'help_text' => $data['fieldHelpText'] ?: null,
                    'required' => $this->fieldRequired,
                ]);
            } else {
                $field->update([
                    'label' => $data['fieldLabel'],
                    'type' => $type,
                    'options' => $options,
                    'help_text' => $data['fieldHelpText'] ?: null,
                    'required' => $this->fieldRequired,
                ]);
            }
        } else {
            EventFormField::create([
                'event_form_id' => $this->eventForm->id,
                'label' => $data['fieldLabel'],
                'type' => $type,
                'options' => $options,
                'help_text' => $data['fieldHelpText'] ?: null,
                'required' => $this->fieldRequired,
                'order' => ($this->eventForm->fields()->max('order') ?? 0) + 1,
            ]);
        }

        $this->showFieldModal = false;
        $this->resetFieldForm();
    }

    public function confirmDeleteField(int $id): void
    {
        $this->authorize('update', $this->eventForm);

        $this->confirmingDeleteFieldId = $id;
    }

    public function closeDeleteFieldModal(): void
    {
        $this->confirmingDeleteFieldId = null;
    }

    public function deleteField(): void
    {
        $this->authorize('update', $this->eventForm);

        if ($this->hasResponses()) {
            $this->confirmingDeleteFieldId = null;

            return;
        }

        $this->eventForm->fields()->findOrFail($this->confirmingDeleteFieldId)->delete();
        $this->confirmingDeleteFieldId = null;
    }

    public function moveFieldUp(int $id): void
    {
        $this->authorize('update', $this->eventForm);
        $this->swapOrder($id, -1);
    }

    public function moveFieldDown(int $id): void
    {
        $this->authorize('update', $this->eventForm);
        $this->swapOrder($id, 1);
    }

    private function swapOrder(int $id, int $direction): void
    {
        $fields = $this->eventForm->fields()->get();
        $index = $fields->search(fn ($field) => $field->id === $id);
        $swapIndex = $index + $direction;

        if ($index === false || ! $fields->has($swapIndex)) {
            return;
        }

        $field = $fields[$index];
        $swapField = $fields[$swapIndex];

        $originalOrder = $field->order;
        $field->update(['order' => $swapField->order]);
        $swapField->update(['order' => $originalOrder]);
    }

    public function render()
    {
        return view('livewire.event-forms.builder', [
            'event' => $this->eventForm->event,
            'fields' => $this->eventForm->fields()->get(),
            'statuses' => EventFormStatus::cases(),
            'fieldTypes' => EventFormFieldType::cases(),
            'checkinEligibleFields' => $this->checkinEligibleFields(),
            'checkinModes' => CheckInVerificationMode::cases(),
            'feedbackForm' => $this->eventForm->type === EventFormType::Registration
                ? $this->eventForm->event->feedbackForm
                : null,
            'pricingEligibleFields' => $this->pricingEligibleFields(),
            'availablePaymentMethods' => $this->availablePaymentMethods(),
        ]);
    }
}
