<?php

namespace Tests\Feature\Livewire\Public;

use App\Enums\EventFormFieldType;
use App\Enums\EventFormStatus;
use App\Livewire\Public\EventRegistration;
use App\Models\Event;
use App\Models\EventForm;
use App\Models\EventFormField;
use App\Models\EventFormResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_submit_a_response(): void
    {
        $eventForm = EventForm::factory()->published()->create();
        $nameField = EventFormField::factory()->for($eventForm, 'eventForm')->create([
            'label' => 'Full Name',
            'type' => EventFormFieldType::Text,
            'required' => true,
        ]);

        Livewire::test(EventRegistration::class, ['eventForm' => $eventForm])
            ->set("answers.{$nameField->id}", 'Ahmad Contoh')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('step', 'done');

        $response = EventFormResponse::first();
        $this->assertNotNull($response);
        $this->assertSame('Ahmad Contoh', $response->answers[$nameField->id]);
    }

    public function test_required_field_is_validated(): void
    {
        $eventForm = EventForm::factory()->published()->create();
        $nameField = EventFormField::factory()->for($eventForm, 'eventForm')->create([
            'type' => EventFormFieldType::Text,
            'required' => true,
        ]);

        Livewire::test(EventRegistration::class, ['eventForm' => $eventForm])
            ->call('submit')
            ->assertHasErrors(["answers.{$nameField->id}"]);

        $this->assertSame(0, EventFormResponse::count());
    }

    public function test_a_closed_form_does_not_accept_submissions(): void
    {
        $eventForm = EventForm::factory()->create(['status' => EventFormStatus::Closed]);

        Livewire::test(EventRegistration::class, ['eventForm' => $eventForm])
            ->call('submit');

        $this->assertSame(0, EventFormResponse::count());
    }

    public function test_preview_shows_a_draft_forms_fields_instead_of_the_closed_message(): void
    {
        $eventForm = EventForm::factory()->create(['status' => EventFormStatus::Draft]);
        EventFormField::factory()->for($eventForm, 'eventForm')->create(['label' => 'Full Name']);

        Livewire::test(EventRegistration::class, ['eventForm' => $eventForm, 'preview' => true])
            ->assertSee('Full Name')
            ->assertDontSee('Registration closed');
    }

    public function test_preview_validates_but_does_not_record_a_response(): void
    {
        $eventForm = EventForm::factory()->create(['status' => EventFormStatus::Draft]);
        $nameField = EventFormField::factory()->for($eventForm, 'eventForm')->create([
            'type' => EventFormFieldType::Text,
            'required' => true,
        ]);

        // Missing the required field still fails validation in preview.
        Livewire::test(EventRegistration::class, ['eventForm' => $eventForm, 'preview' => true])
            ->call('submit')
            ->assertHasErrors(["answers.{$nameField->id}"]);

        Livewire::test(EventRegistration::class, ['eventForm' => $eventForm, 'preview' => true])
            ->set("answers.{$nameField->id}", 'Ahmad Contoh')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('step', 'done');

        $this->assertSame(0, EventFormResponse::count());
    }

    public function test_submitting_a_feedback_survey_shows_feedback_flavored_copy(): void
    {
        $event = Event::factory()->create();
        $feedbackForm = EventForm::factory()->for($event)->feedback()->published()->create();
        $ratingField = EventFormField::factory()->for($feedbackForm, 'eventForm')->create([
            'type' => EventFormFieldType::Text,
        ]);

        Livewire::test(EventRegistration::class, ['eventForm' => $feedbackForm])
            ->set("answers.{$ratingField->id}", 'Great event!')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('step', 'done')
            ->assertSee('Feedback submitted')
            ->assertDontSee('Registration submitted');

        $response = EventFormResponse::first();
        $this->assertSame('Great event!', $response->answers[$ratingField->id]);
    }
}
