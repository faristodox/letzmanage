<?php

namespace Tests\Feature\Livewire\Public;

use App\Enums\FormFieldType;
use App\Enums\FormStatus;
use App\Livewire\Public\FormSubmission;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FormSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_submit_a_response(): void
    {
        $form = Form::factory()->published()->create();
        $nameField = FormField::factory()->for($form, 'form')->create([
            'label' => 'Full Name',
            'type' => FormFieldType::Text,
            'required' => true,
        ]);

        Livewire::test(FormSubmission::class, ['form' => $form])
            ->set("answers.{$nameField->id}", 'Ahmad Contoh')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $response = FormResponse::first();
        $this->assertNotNull($response);
        $this->assertSame('Ahmad Contoh', $response->answers[$nameField->id]);
    }

    public function test_required_field_is_validated(): void
    {
        $form = Form::factory()->published()->create();
        $nameField = FormField::factory()->for($form, 'form')->create([
            'type' => FormFieldType::Text,
            'required' => true,
        ]);

        Livewire::test(FormSubmission::class, ['form' => $form])
            ->call('submit')
            ->assertHasErrors(["answers.{$nameField->id}"]);

        $this->assertSame(0, FormResponse::count());
    }

    public function test_a_closed_form_does_not_accept_submissions(): void
    {
        $form = Form::factory()->create(['status' => FormStatus::Closed]);

        Livewire::test(FormSubmission::class, ['form' => $form])
            ->call('submit');

        $this->assertSame(0, FormResponse::count());
    }

    public function test_preview_shows_a_draft_forms_fields_instead_of_the_closed_message(): void
    {
        $form = Form::factory()->create(['status' => FormStatus::Draft]);
        FormField::factory()->for($form, 'form')->create(['label' => 'Full Name']);

        Livewire::test(FormSubmission::class, ['form' => $form, 'preview' => true])
            ->assertSee('Full Name')
            ->assertDontSee('Form closed');
    }

    public function test_preview_validates_but_does_not_record_a_response(): void
    {
        $form = Form::factory()->create(['status' => FormStatus::Draft]);
        $nameField = FormField::factory()->for($form, 'form')->create([
            'type' => FormFieldType::Text,
            'required' => true,
        ]);

        // Missing the required field still fails validation in preview.
        Livewire::test(FormSubmission::class, ['form' => $form, 'preview' => true])
            ->call('submit')
            ->assertHasErrors(["answers.{$nameField->id}"]);

        Livewire::test(FormSubmission::class, ['form' => $form, 'preview' => true])
            ->set("answers.{$nameField->id}", 'Ahmad Contoh')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $this->assertSame(0, FormResponse::count());
    }

    public function test_guest_can_upload_a_file(): void
    {
        Storage::fake('public');

        $form = Form::factory()->published()->create();
        $uploadField = FormField::factory()->for($form, 'form')->create([
            'label' => 'Resume',
            'type' => FormFieldType::File,
            'required' => true,
        ]);

        Livewire::test(FormSubmission::class, ['form' => $form])
            ->set("answers.{$uploadField->id}", UploadedFile::fake()->create('resume.pdf', 500))
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $response = FormResponse::first();
        $this->assertNotNull($response);
        $path = $response->answers[$uploadField->id];
        $this->assertIsString($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_a_required_file_field_is_validated(): void
    {
        $form = Form::factory()->published()->create();
        $uploadField = FormField::factory()->for($form, 'form')->create([
            'type' => FormFieldType::File,
            'required' => true,
        ]);

        Livewire::test(FormSubmission::class, ['form' => $form])
            ->call('submit')
            ->assertHasErrors(["answers.{$uploadField->id}"]);

        $this->assertSame(0, FormResponse::count());
    }
}
