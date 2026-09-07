<?php

namespace App\Livewire\Forms;

use App\Enums\FormFieldType;
use App\Enums\FormStatus;
use App\Models\Form;
use App\Models\FormField;
use Livewire\Component;

class Builder extends Component
{
    public Form $form;

    public string $formTitle = '';

    public string $formDescription = '';

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

    public function mount(Form $form): void
    {
        $this->authorize('update', $form);

        $this->form = $form;
        $this->formTitle = $form->title;
        $this->formDescription = (string) $form->description;
        $this->status = $form->status->value;
        $this->closes_at = $form->closes_at?->format('Y-m-d\TH:i') ?? '';
    }

    public function saveFormSettings(): void
    {
        $this->authorize('update', $this->form);

        $data = $this->validate([
            'formTitle' => ['required', 'string', 'max:255'],
            'formDescription' => ['nullable', 'string'],
            'status' => ['required', 'string'],
            'closes_at' => ['nullable', 'date'],
        ]);

        $this->form->update([
            'title' => $data['formTitle'],
            'description' => $data['formDescription'] ?: null,
            'status' => FormStatus::from($data['status']),
            'closes_at' => $data['closes_at'] ?: null,
        ]);

        session()->flash('status', __('Form settings saved.'));
    }

    public function hasResponses(): bool
    {
        return $this->form->responses()->exists();
    }

    public function addField(): void
    {
        $this->authorize('update', $this->form);

        $this->resetFieldForm();
        $this->fieldType = FormFieldType::Text->value;
        $this->showFieldModal = true;
    }

    public function editField(int $id): void
    {
        $this->authorize('update', $this->form);

        $field = $this->form->fields()->findOrFail($id);

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
        $this->authorize('update', $this->form);

        $data = $this->validate([
            'fieldLabel' => ['required', 'string', 'max:255'],
            'fieldType' => ['required', 'string'],
            'fieldHelpText' => ['nullable', 'string'],
            'fieldOptions' => ['nullable', 'string'],
        ]);
        $type = FormFieldType::from($data['fieldType']);

        $options = $type->isChoice()
            ? collect(preg_split('/\r\n|\r|\n/', $data['fieldOptions']))
                ->map(fn ($line) => trim($line))
                ->filter()
                ->values()
                ->all()
            : null;

        if ($this->editingFieldId) {
            $field = $this->form->fields()->findOrFail($this->editingFieldId);

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
            FormField::create([
                'form_id' => $this->form->id,
                'label' => $data['fieldLabel'],
                'type' => $type,
                'options' => $options,
                'help_text' => $data['fieldHelpText'] ?: null,
                'required' => $this->fieldRequired,
                'order' => ($this->form->fields()->max('order') ?? 0) + 1,
            ]);
        }

        $this->showFieldModal = false;
        $this->resetFieldForm();
    }

    public function confirmDeleteField(int $id): void
    {
        $this->authorize('update', $this->form);

        $this->confirmingDeleteFieldId = $id;
    }

    public function closeDeleteFieldModal(): void
    {
        $this->confirmingDeleteFieldId = null;
    }

    public function deleteField(): void
    {
        $this->authorize('update', $this->form);

        if ($this->hasResponses()) {
            $this->confirmingDeleteFieldId = null;

            return;
        }

        $this->form->fields()->findOrFail($this->confirmingDeleteFieldId)->delete();
        $this->confirmingDeleteFieldId = null;
    }

    public function moveFieldUp(int $id): void
    {
        $this->authorize('update', $this->form);
        $this->swapOrder($id, -1);
    }

    public function moveFieldDown(int $id): void
    {
        $this->authorize('update', $this->form);
        $this->swapOrder($id, 1);
    }

    private function swapOrder(int $id, int $direction): void
    {
        $fields = $this->form->fields()->get();
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
        return view('livewire.forms.builder', [
            'fields' => $this->form->fields()->get(),
            'statuses' => FormStatus::cases(),
            'fieldTypes' => FormFieldType::cases(),
        ]);
    }
}
