<?php

namespace App\Livewire\Public;

use App\Enums\FormFieldType;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class FormSubmission extends Component
{
    use WithFileUploads;

    public ?int $organizationId = null;

    public ?int $formId = null;

    public array $answers = [];

    public bool $submitted = false;

    public bool $preview = false;

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

    public function mount(Form $form, bool $preview = false): void
    {
        $this->organizationId = app(CurrentOrganization::class)->id();
        $this->formId = $form->id;
        $this->preview = $preview;
    }

    private function form(): Form
    {
        return Form::with('fields')->findOrFail($this->formId);
    }

    private function rulesFor(Form $form): array
    {
        $rules = [];

        foreach ($form->fields as $field) {
            $key = "answers.{$field->id}";
            $prefix = $field->required ? 'required' : 'nullable';

            $rules[$key] = match ($field->type) {
                FormFieldType::Email => [$prefix, 'email'],
                FormFieldType::Number => [$prefix, 'numeric'],
                FormFieldType::Date => [$prefix, 'date'],
                FormFieldType::Checkbox => [$field->required ? 'required' : 'nullable', 'array'],
                FormFieldType::Select, FormFieldType::Radio => [$prefix, 'string', Rule::in($field->options ?? [])],
                FormFieldType::File => [$prefix, 'file', 'max:10240'],
                default => [$prefix, 'string', 'max:2000'],
            };

            if ($field->type === FormFieldType::Checkbox) {
                $rules["{$key}.*"] = [Rule::in($field->options ?? [])];
            }
        }

        return $rules;
    }

    public function submit(): void
    {
        $form = $this->form();

        if (! $this->preview && ! $form->isAcceptingResponses()) {
            return;
        }

        $this->validate($this->rulesFor($form));

        // Preview runs the same validation a real visitor would hit, but never
        // writes a response (or stores an uploaded file) — it's for the form
        // owner to sanity-check the form.
        if (! $this->preview) {
            $answers = $this->answers;

            foreach ($form->fields as $field) {
                if ($field->type === FormFieldType::File && ($answers[$field->id] ?? null) instanceof UploadedFile) {
                    $answers[$field->id] = $answers[$field->id]->store('form-uploads', 'public');
                }
            }

            FormResponse::create([
                'form_id' => $form->id,
                'answers' => $answers,
                'submitted_ip' => request()->ip(),
            ]);
        }

        $this->submitted = true;
    }

    public function render()
    {
        $form = $this->form();

        return view('livewire.public.form-submission', [
            'form' => $form,
        ]);
    }
}
