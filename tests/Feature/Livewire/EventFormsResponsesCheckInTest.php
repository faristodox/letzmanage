<?php

namespace Tests\Feature\Livewire;

use App\Enums\EventCheckInMethod;
use App\Enums\RoleName;
use App\Livewire\EventForms\Responses;
use App\Models\EventCheckIn;
use App\Models\EventForm;
use App\Models\EventFormResponse;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventFormsResponsesCheckInTest extends TestCase
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

    public function test_admin_can_manually_check_in_a_response(): void
    {
        $eventForm = EventForm::factory()->checkinEnabled()->create();
        $response = EventFormResponse::factory()->for($eventForm, 'eventForm')->create();

        Livewire::actingAs($this->admin())
            ->test(Responses::class, ['eventForm' => $eventForm])
            ->call('checkIn', $response->id);

        $checkIn = EventCheckIn::where('event_form_response_id', $response->id)->first();
        $this->assertNotNull($checkIn);
        $this->assertSame(EventCheckInMethod::Manual, $checkIn->method);
    }

    public function test_checking_in_twice_does_not_create_duplicate_records(): void
    {
        $eventForm = EventForm::factory()->checkinEnabled()->create();
        $response = EventFormResponse::factory()->for($eventForm, 'eventForm')->create();

        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Responses::class, ['eventForm' => $eventForm])->call('checkIn', $response->id);
        Livewire::actingAs($admin)->test(Responses::class, ['eventForm' => $eventForm])->call('checkIn', $response->id);

        $this->assertSame(1, EventCheckIn::where('event_form_response_id', $response->id)->count());
    }

    public function test_admin_can_undo_a_check_in(): void
    {
        $eventForm = EventForm::factory()->checkinEnabled()->create();
        $response = EventFormResponse::factory()->for($eventForm, 'eventForm')->create();
        EventCheckIn::factory()->create(['event_form_id' => $eventForm->id, 'event_form_response_id' => $response->id]);

        Livewire::actingAs($this->admin())
            ->test(Responses::class, ['eventForm' => $eventForm])
            ->call('undoCheckIn', $response->id);

        $this->assertSame(0, EventCheckIn::where('event_form_response_id', $response->id)->count());
    }

    public function test_door_staff_cannot_undo_a_check_in(): void
    {
        $eventForm = EventForm::factory()->checkinEnabled()->create();
        $response = EventFormResponse::factory()->for($eventForm, 'eventForm')->create();
        EventCheckIn::factory()->create(['event_form_id' => $eventForm->id, 'event_form_response_id' => $response->id]);

        $staff = User::factory()->create();
        $staff->assignRole(RoleName::Staff->value);
        $staff->givePermissionTo('check in event participants');

        Livewire::actingAs($staff)
            ->test(Responses::class, ['eventForm' => $eventForm])
            ->call('undoCheckIn', $response->id)
            ->assertForbidden();
    }

    public function test_stats_and_search_reflect_check_in_state(): void
    {
        $eventForm = EventForm::factory()->checkinEnabled()->create();

        $checkedIn = EventFormResponse::factory()->for($eventForm, 'eventForm')->create(['answers' => ['1' => 'Ahmad Contoh']]);
        EventCheckIn::factory()->create([
            'event_form_id' => $eventForm->id,
            'event_form_response_id' => $checkedIn->id,
            'method' => EventCheckInMethod::Onsite,
        ]);

        EventFormResponse::factory()->for($eventForm, 'eventForm')->create(['answers' => ['1' => 'Siti Aminah']]);

        $component = Livewire::actingAs($this->admin())->test(Responses::class, ['eventForm' => $eventForm]);

        $this->assertSame(2, $component->viewData('totalCount'));
        $this->assertSame(1, $component->viewData('checkedInCount'));
        $this->assertSame(1, $component->viewData('notCheckedInCount'));
        $this->assertSame(1, $component->viewData('onsiteCount'));

        $component->set('search', 'Ahmad');
        $this->assertCount(1, $component->viewData('responses'));
        $this->assertSame($checkedIn->id, $component->viewData('responses')->first()->id);
    }

    public function test_export_includes_checkin_columns_when_enabled(): void
    {
        $eventForm = EventForm::factory()->checkinEnabled()->create();
        $response = EventFormResponse::factory()->for($eventForm, 'eventForm')->create();
        EventCheckIn::factory()->create([
            'event_form_id' => $eventForm->id,
            'event_form_response_id' => $response->id,
            'method' => EventCheckInMethod::Qr,
        ]);

        $this->actingAs($this->admin());

        $component = new Responses;
        $component->eventForm = $eventForm;

        ob_start();
        $component->export()->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString('Check-in Status', $csv);
        $this->assertStringContainsString('Checked In', $csv);
        $this->assertStringContainsString('Qr', $csv);
    }
}
