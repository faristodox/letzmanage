<?php

namespace Tests\Feature\Livewire\Public;

use App\Enums\CheckInVerificationMode;
use App\Enums\EventCheckInMethod;
use App\Enums\EventFormFieldType;
use App\Livewire\Public\EventCheckIn;
use App\Models\EventCheckIn as EventCheckInModel;
use App\Models\EventForm;
use App\Models\EventFormField;
use App\Models\EventFormResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventCheckInTest extends TestCase
{
    use RefreshDatabase;

    private function formWithEmailVerification(array $overrides = []): array
    {
        $eventForm = EventForm::factory()->checkinEnabled($overrides)->create();
        $emailField = EventFormField::factory()->for($eventForm, 'eventForm')->create(['type' => EventFormFieldType::Email]);

        $eventForm->update(['checkin_verification_field_ids' => [$emailField->id]]);

        return [$eventForm, $emailField];
    }

    public function test_registered_participant_can_verify_confirm_and_check_in(): void
    {
        [$eventForm, $emailField] = $this->formWithEmailVerification();
        $response = EventFormResponse::factory()->for($eventForm, 'eventForm')->create([
            'answers' => [$emailField->id => 'ahmad@example.com'],
        ]);

        Livewire::test(EventCheckIn::class, ['eventForm' => $eventForm])
            ->set("verify.{$emailField->id}", 'AHMAD@example.com') // case-insensitive match
            ->call('submitVerification')
            ->assertSet('step', 'found')
            ->call('confirmCheckIn')
            ->assertSet('step', 'success');

        $this->assertSame(1, EventCheckInModel::where('event_form_response_id', $response->id)->count());
    }

    public function test_already_checked_in_participant_sees_already_state(): void
    {
        [$eventForm, $emailField] = $this->formWithEmailVerification();
        $response = EventFormResponse::factory()->for($eventForm, 'eventForm')->create([
            'answers' => [$emailField->id => 'ahmad@example.com'],
        ]);
        EventCheckInModel::factory()->create([
            'event_form_id' => $eventForm->id,
            'event_form_response_id' => $response->id,
        ]);

        Livewire::test(EventCheckIn::class, ['eventForm' => $eventForm])
            ->set("verify.{$emailField->id}", 'ahmad@example.com')
            ->call('submitVerification')
            ->assertSet('step', 'already');
    }

    public function test_unregistered_participant_with_onsite_registration_enabled_can_register_then_check_in(): void
    {
        [$eventForm, $emailField] = $this->formWithEmailVerification(['checkin_onsite_registration_enabled' => true]);

        $component = Livewire::test(EventCheckIn::class, ['eventForm' => $eventForm])
            ->set("verify.{$emailField->id}", 'nobody@example.com')
            ->call('submitVerification')
            ->assertSet('step', 'not_found')
            ->call('startOnsiteRegistration')
            ->assertSet('step', 'register');

        // Simulate the embedded EventRegistration component having created a
        // response and dispatched event-form-submitted (tested directly on
        // the listener, since Livewire::test doesn't propagate nested-component
        // dispatches on its own).
        $response = EventFormResponse::create([
            'event_form_id' => $eventForm->id,
            'answers' => [$emailField->id => 'nobody@example.com'],
        ]);

        $component->call('onRegistered', $response->id)->assertSet('step', 'success');

        $checkIn = EventCheckInModel::where('event_form_response_id', $response->id)->first();
        $this->assertNotNull($checkIn);
        $this->assertSame(EventCheckInMethod::Onsite, $checkIn->method);
    }

    public function test_unregistered_participant_with_onsite_registration_disabled_cannot_register(): void
    {
        [$eventForm, $emailField] = $this->formWithEmailVerification(['checkin_onsite_registration_enabled' => false]);

        Livewire::test(EventCheckIn::class, ['eventForm' => $eventForm])
            ->set("verify.{$emailField->id}", 'nobody@example.com')
            ->call('submitVerification')
            ->assertSet('step', 'not_found')
            ->call('startOnsiteRegistration')
            ->assertSet('step', 'not_found'); // unchanged — guarded server-side
    }

    public function test_match_all_requires_every_selected_field(): void
    {
        $eventForm = EventForm::factory()->checkinEnabled(['checkin_verification_mode' => CheckInVerificationMode::All])->create();
        $emailField = EventFormField::factory()->for($eventForm, 'eventForm')->create(['type' => EventFormFieldType::Email]);
        $phoneField = EventFormField::factory()->for($eventForm, 'eventForm')->create(['type' => EventFormFieldType::Phone]);
        $eventForm->update(['checkin_verification_field_ids' => [$emailField->id, $phoneField->id]]);

        EventFormResponse::factory()->for($eventForm, 'eventForm')->create([
            'answers' => [$emailField->id => 'ahmad@example.com', $phoneField->id => '0123456789'],
        ]);

        // Correct email, wrong phone — Match ALL must fail to find a match.
        Livewire::test(EventCheckIn::class, ['eventForm' => $eventForm])
            ->set("verify.{$emailField->id}", 'ahmad@example.com')
            ->set("verify.{$phoneField->id}", '0000000000')
            ->call('submitVerification')
            ->assertSet('step', 'not_found');

        // Both correct — matches.
        Livewire::test(EventCheckIn::class, ['eventForm' => $eventForm])
            ->set("verify.{$emailField->id}", 'ahmad@example.com')
            ->set("verify.{$phoneField->id}", '0123456789')
            ->call('submitVerification')
            ->assertSet('step', 'found');
    }

    public function test_match_any_succeeds_with_only_one_correct_field(): void
    {
        $eventForm = EventForm::factory()->checkinEnabled(['checkin_verification_mode' => CheckInVerificationMode::Any])->create();
        $emailField = EventFormField::factory()->for($eventForm, 'eventForm')->create(['type' => EventFormFieldType::Email]);
        $phoneField = EventFormField::factory()->for($eventForm, 'eventForm')->create(['type' => EventFormFieldType::Phone]);
        $eventForm->update(['checkin_verification_field_ids' => [$emailField->id, $phoneField->id]]);

        EventFormResponse::factory()->for($eventForm, 'eventForm')->create([
            'answers' => [$emailField->id => 'ahmad@example.com', $phoneField->id => '0123456789'],
        ]);

        Livewire::test(EventCheckIn::class, ['eventForm' => $eventForm])
            ->set("verify.{$emailField->id}", 'ahmad@example.com')
            ->set("verify.{$phoneField->id}", '0000000000') // wrong, but Any only needs one
            ->call('submitVerification')
            ->assertSet('step', 'found');
    }

    public function test_confirming_check_in_twice_does_not_create_duplicate_records(): void
    {
        [$eventForm, $emailField] = $this->formWithEmailVerification();
        $response = EventFormResponse::factory()->for($eventForm, 'eventForm')->create([
            'answers' => [$emailField->id => 'ahmad@example.com'],
        ]);

        // Two independent "tabs" both find the same unchecked-in registration...
        $tabOne = Livewire::test(EventCheckIn::class, ['eventForm' => $eventForm])
            ->set("verify.{$emailField->id}", 'ahmad@example.com')
            ->call('submitVerification');

        $tabTwo = Livewire::test(EventCheckIn::class, ['eventForm' => $eventForm])
            ->set("verify.{$emailField->id}", 'ahmad@example.com')
            ->call('submitVerification');

        // ...and both try to confirm.
        $tabOne->call('confirmCheckIn')->assertSet('step', 'success');
        $tabTwo->call('confirmCheckIn');

        $this->assertSame(1, EventCheckInModel::where('event_form_response_id', $response->id)->count());
    }
}
