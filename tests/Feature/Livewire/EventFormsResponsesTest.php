<?php

namespace Tests\Feature\Livewire;

use App\Enums\EventFormFieldType;
use App\Enums\RoleName;
use App\Livewire\EventForms\Responses;
use App\Models\EventForm;
use App\Models\EventFormField;
use App\Models\EventFormResponse;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventFormsResponsesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_delete_a_response(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $eventForm = EventForm::factory()->create();
        $response = EventFormResponse::factory()->for($eventForm, 'eventForm')->create();

        Livewire::actingAs($admin)
            ->test(Responses::class, ['eventForm' => $eventForm])
            ->call('confirmDelete', $response->id)
            ->call('delete');

        $this->assertSame(0, $eventForm->responses()->count());
    }

    public function test_analysis_counts_choice_field_answers(): void
    {
        $eventForm = EventForm::factory()->create();
        $field = EventFormField::factory()->for($eventForm, 'eventForm')->create([
            'type' => EventFormFieldType::Select,
            'options' => ['Chicken', 'Beef'],
        ]);

        EventFormResponse::factory()->for($eventForm, 'eventForm')->create(['answers' => [$field->id => 'Chicken']]);
        EventFormResponse::factory()->for($eventForm, 'eventForm')->create(['answers' => [$field->id => 'Chicken']]);
        EventFormResponse::factory()->for($eventForm, 'eventForm')->create(['answers' => [$field->id => 'Beef']]);

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $charts = Livewire::actingAs($admin)
            ->test(Responses::class, ['eventForm' => $eventForm])
            ->viewData('charts');

        $chart = $charts->first();
        $counts = array_combine($chart['labels'], $chart['data']);

        $this->assertSame(2, $counts['Chicken']);
        $this->assertSame(1, $counts['Beef']);
    }

    public function test_manager_with_view_only_permission_cannot_delete_a_response(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('view event responses');

        $eventForm = EventForm::factory()->create();
        $response = EventFormResponse::factory()->for($eventForm, 'eventForm')->create();

        Livewire::actingAs($viewer)
            ->test(Responses::class, ['eventForm' => $eventForm])
            ->call('confirmDelete', $response->id)
            ->assertForbidden();
    }
}
