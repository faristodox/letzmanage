<?php

namespace Tests\Feature\Livewire;

use App\Enums\CheckInVerificationMode;
use App\Enums\EventFormFieldType;
use App\Enums\RoleName;
use App\Livewire\EventForms\Builder;
use App\Models\EventForm;
use App\Models\EventFormField;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventFormsBuilderCheckinSettingsTest extends TestCase
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

    public function test_admin_can_enable_checkin_with_verification_fields(): void
    {
        $eventForm = EventForm::factory()->create();
        $emailField = EventFormField::factory()->for($eventForm, 'eventForm')->create(['type' => EventFormFieldType::Email]);

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['eventForm' => $eventForm])
            ->set('checkinEnabled', true)
            ->set('checkinVerificationFieldIds', [$emailField->id])
            ->set('checkinVerificationMode', CheckInVerificationMode::All->value)
            ->call('saveCheckinSettings')
            ->assertHasNoErrors();

        $eventForm->refresh();
        $this->assertTrue($eventForm->checkin_enabled);
        $this->assertSame([$emailField->id], $eventForm->checkin_verification_field_ids);
        $this->assertSame(CheckInVerificationMode::All, $eventForm->checkin_verification_mode);
    }

    public function test_enabling_checkin_without_a_verification_field_fails_validation(): void
    {
        $eventForm = EventForm::factory()->create();
        EventFormField::factory()->for($eventForm, 'eventForm')->create();

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['eventForm' => $eventForm])
            ->set('checkinEnabled', true)
            ->set('checkinVerificationFieldIds', [])
            ->call('saveCheckinSettings')
            ->assertHasErrors(['checkinVerificationFieldIds']);

        $this->assertFalse($eventForm->refresh()->checkin_enabled);
    }

    public function test_a_field_id_from_another_form_is_rejected(): void
    {
        $eventForm = EventForm::factory()->create();
        EventFormField::factory()->for($eventForm, 'eventForm')->create();

        $otherForm = EventForm::factory()->create();
        $foreignField = EventFormField::factory()->for($otherForm, 'eventForm')->create();

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['eventForm' => $eventForm])
            ->set('checkinEnabled', true)
            ->set('checkinVerificationFieldIds', [$foreignField->id])
            ->call('saveCheckinSettings')
            ->assertHasErrors(['checkinVerificationFieldIds.0']);
    }

    public function test_checkbox_type_fields_are_not_eligible_for_verification(): void
    {
        $eventForm = EventForm::factory()->create();
        $checkboxField = EventFormField::factory()->for($eventForm, 'eventForm')->create([
            'type' => EventFormFieldType::Checkbox,
            'options' => ['A', 'B'],
        ]);

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['eventForm' => $eventForm])
            ->set('checkinEnabled', true)
            ->set('checkinVerificationFieldIds', [$checkboxField->id])
            ->call('saveCheckinSettings')
            ->assertHasErrors(['checkinVerificationFieldIds.0']);
    }
}
