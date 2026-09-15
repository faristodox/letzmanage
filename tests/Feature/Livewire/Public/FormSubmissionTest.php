<?php

namespace Tests\Feature\Livewire\Public;

use App\Enums\FormFieldType;
use App\Enums\FormStatus;
use App\Livewire\Public\FormSubmission;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormResponse;
use App\Models\Organization;
use App\Models\SpiMember;
use App\Support\CurrentOrganization;
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

    public function test_ic_number_field_requires_a_valid_12_digit_mykad_format(): void
    {
        $form = Form::factory()->published()->create();
        $icField = FormField::factory()->for($form, 'form')->create([
            'type' => FormFieldType::IcNumber,
            'required' => true,
        ]);

        Livewire::test(FormSubmission::class, ['form' => $form])
            ->set("answers.{$icField->id}", '12345')
            ->call('submit')
            ->assertHasErrors(["answers.{$icField->id}"]);

        $this->assertSame(0, FormResponse::count());
    }

    public function test_ic_number_field_normalizes_dashes_before_validating_and_storing(): void
    {
        $form = Form::factory()->published()->create();
        $icField = FormField::factory()->for($form, 'form')->create([
            'type' => FormFieldType::IcNumber,
            'required' => true,
        ]);

        Livewire::test(FormSubmission::class, ['form' => $form])
            ->set("answers.{$icField->id}", '901231-14-5678')
            ->call('submit')
            ->assertHasNoErrors();

        $response = FormResponse::first();
        $this->assertSame('901231145678', $response->answers[$icField->id]);
    }

    public function test_ic_number_field_rejects_a_number_that_does_not_match_a_registered_member_when_verification_is_enabled(): void
    {
        $organization = Organization::factory()->create(['spi_enabled' => true]);
        $ctx = app(CurrentOrganization::class);

        $form = $ctx->runFor($organization, fn () => Form::factory()->published()->create());
        $icField = $ctx->runFor($organization, fn () => FormField::factory()->for($form, 'form')->create([
            'type' => FormFieldType::IcNumber,
            'required' => true,
            'verify_spi_membership' => true,
        ]));

        $ctx->set($organization);

        Livewire::test(FormSubmission::class, ['form' => $form])
            ->set("answers.{$icField->id}", '901231145678')
            ->call('submit')
            ->assertHasErrors(["answers.{$icField->id}"]);

        $this->assertSame(0, FormResponse::count());
    }

    public function test_ic_number_field_accepts_a_number_that_matches_a_registered_member(): void
    {
        $organization = Organization::factory()->create(['spi_enabled' => true]);
        $ctx = app(CurrentOrganization::class);

        $form = $ctx->runFor($organization, fn () => Form::factory()->published()->create());
        $icField = $ctx->runFor($organization, fn () => FormField::factory()->for($form, 'form')->create([
            'type' => FormFieldType::IcNumber,
            'required' => true,
            'verify_spi_membership' => true,
        ]));
        $ctx->runFor($organization, fn () => SpiMember::create([
            'no_ahli' => 'A001',
            'nama' => 'Ahmad Contoh',
            'no_kp' => '901231145678',
            'level' => '00',
        ]));

        $ctx->set($organization);

        Livewire::test(FormSubmission::class, ['form' => $form])
            ->set("answers.{$icField->id}", '901231145678')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $this->assertSame(1, FormResponse::count());
    }

    public function test_ic_number_field_does_not_check_membership_for_another_organizations_member(): void
    {
        $organization = Organization::factory()->create(['spi_enabled' => true]);
        $otherOrganization = Organization::factory()->create(['spi_enabled' => true]);
        $ctx = app(CurrentOrganization::class);

        $form = $ctx->runFor($organization, fn () => Form::factory()->published()->create());
        $icField = $ctx->runFor($organization, fn () => FormField::factory()->for($form, 'form')->create([
            'type' => FormFieldType::IcNumber,
            'required' => true,
            'verify_spi_membership' => true,
        ]));
        // Same IC number, but registered under a different organization.
        $ctx->runFor($otherOrganization, fn () => SpiMember::create([
            'no_ahli' => 'B001',
            'nama' => 'Someone Else',
            'no_kp' => '901231145678',
            'level' => '00',
        ]));

        $ctx->set($organization);

        Livewire::test(FormSubmission::class, ['form' => $form])
            ->set("answers.{$icField->id}", '901231145678')
            ->call('submit')
            ->assertHasErrors(["answers.{$icField->id}"]);

        $this->assertSame(0, FormResponse::count());
    }
}
