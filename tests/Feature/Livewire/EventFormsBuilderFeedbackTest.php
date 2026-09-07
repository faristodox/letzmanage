<?php

namespace Tests\Feature\Livewire;

use App\Enums\EventFormFieldType;
use App\Enums\EventFormType;
use App\Enums\RoleName;
use App\Livewire\EventForms\Builder;
use App\Models\Event;
use App\Models\EventForm;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventFormsBuilderFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        return $admin;
    }

    public function test_admin_can_add_a_feedback_survey_from_the_registration_forms_builder(): void
    {
        $event = Event::factory()->create();
        $registrationForm = EventForm::factory()->for($event)->create();

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['eventForm' => $registrationForm])
            ->call('addFeedbackForm');

        $feedbackForm = $event->feedbackForm()->first();
        $this->assertNotNull($feedbackForm);
        $this->assertSame(EventFormType::Feedback, $feedbackForm->type);
        $this->assertSame($event->id, $feedbackForm->event_id);
    }

    public function test_a_second_feedback_survey_cannot_be_added(): void
    {
        $event = Event::factory()->create();
        $registrationForm = EventForm::factory()->for($event)->create();
        EventForm::factory()->for($event)->feedback()->create();

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['eventForm' => $registrationForm])
            ->call('addFeedbackForm');

        $this->assertSame(1, EventForm::where('event_id', $event->id)->where('type', EventFormType::Feedback)->count());
    }

    public function test_a_feedback_survey_cannot_spawn_another_feedback_survey(): void
    {
        $event = Event::factory()->create();
        $feedbackForm = EventForm::factory()->for($event)->feedback()->create();

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['eventForm' => $feedbackForm])
            ->call('addFeedbackForm');

        $this->assertSame(1, EventForm::where('event_id', $event->id)->count());
    }

    public function test_admin_can_delete_the_feedback_survey_from_the_registration_forms_builder(): void
    {
        $event = Event::factory()->create();
        $registrationForm = EventForm::factory()->for($event)->create();
        EventForm::factory()->for($event)->feedback()->create();

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['eventForm' => $registrationForm])
            ->call('confirmDeleteFeedbackForm')
            ->call('deleteFeedbackForm');

        $this->assertSame(0, EventForm::where('event_id', $event->id)->where('type', EventFormType::Feedback)->count());
    }

    public function test_the_feedback_survey_reuses_the_same_field_builder(): void
    {
        $event = Event::factory()->create();
        $feedbackForm = EventForm::factory()->for($event)->feedback()->create();

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['eventForm' => $feedbackForm])
            ->call('addField')
            ->set('fieldLabel', 'How was the event?')
            ->set('fieldType', EventFormFieldType::Textarea->value)
            ->call('saveField')
            ->assertHasNoErrors();

        $this->assertSame('How was the event?', $feedbackForm->fields()->first()->label);
    }
}
