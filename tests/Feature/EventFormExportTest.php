<?php

namespace Tests\Feature;

use App\Enums\EventFormFieldType;
use App\Enums\RoleName;
use App\Livewire\EventForms\Responses;
use App\Models\Event;
use App\Models\EventForm;
use App\Models\EventFormField;
use App\Models\EventFormResponse;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventFormExportTest extends TestCase
{
    use RefreshDatabase;

    private function csvFrom($response): string
    {
        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_export_streams_csv_with_field_labels_as_headers(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $this->actingAs($admin);

        $event = Event::factory()->create(['title' => 'Annual Dinner']);
        $eventForm = EventForm::factory()->for($event)->create();

        $nameField = EventFormField::factory()->for($eventForm, 'eventForm')->create(['label' => 'Full Name', 'type' => EventFormFieldType::Text, 'order' => 1]);
        $phoneField = EventFormField::factory()->for($eventForm, 'eventForm')->create(['label' => 'Phone', 'type' => EventFormFieldType::Phone, 'order' => 2]);
        $mealField = EventFormField::factory()->for($eventForm, 'eventForm')->create(['label' => 'Meals', 'type' => EventFormFieldType::Checkbox, 'options' => ['Chicken', 'Beef'], 'order' => 3]);

        EventFormResponse::factory()->for($eventForm, 'eventForm')->create([
            'answers' => [
                $nameField->id => 'Ahmad Contoh',
                $phoneField->id => '0123456789',
                $mealField->id => ['Chicken', 'Beef'],
            ],
        ]);

        $component = new Responses;
        $component->eventForm = $eventForm;

        $csv = $this->csvFrom($component->export());

        $this->assertStringContainsString('Full Name', $csv);
        $this->assertStringContainsString('Phone', $csv);
        $this->assertStringContainsString('Meals', $csv);
        $this->assertStringContainsString('Ahmad Contoh', $csv);
        $this->assertStringContainsString('=""0123456789""', $csv); // phone kept as Excel text (CSV-quoted)
        $this->assertStringContainsString('Chicken, Beef', $csv);
    }
}
