<?php

namespace App\Livewire\Public;

use App\Enums\FormFieldType;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Organization;
use App\Models\SpiMember;
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
                FormFieldType::IcNumber => [
                    $prefix, 'digits:12',
                    ...($field->verify_spi_membership ? [$this->matchesSpiMemberRule()] : []),
                ],
                default => [$prefix, 'string', 'max:2000'],
            };

            if ($field->type === FormFieldType::Checkbox) {
                $rules["{$key}.*"] = [Rule::in($field->options ?? [])];
            }
        }

        return $rules;
    }

    /**
     * Rejects an IC number that doesn't belong to any member in this
     * organization's SPI data. SpiMember carries its own organization scope
     * (kept in sync by boot(), same as every other query this component
     * makes), so this never leaks across tenants.
     */
    private function matchesSpiMemberRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value && ! SpiMember::where('no_kp', $value)->exists()) {
                $fail(__('This IC number does not match any registered member.'));
            }
        };
    }

    public function submit(): void
    {
        $form = $this->form();

        if (! $this->preview && ! $form->isAcceptingResponses()) {
            return;
        }

        // Accept an IC number typed with the usual dashes/spaces (e.g.
        // "901231-14-5678") — normalize to digits-only before validating so
        // both the digits:12 format check and the SPI lookup below see the
        // same clean value that's stored in spi_members.no_kp.
        foreach ($form->fields as $field) {
            if ($field->type === FormFieldType::IcNumber && isset($this->answers[$field->id])) {
                $this->answers[$field->id] = preg_replace('/\D+/', '', (string) $this->answers[$field->id]);
            }
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
