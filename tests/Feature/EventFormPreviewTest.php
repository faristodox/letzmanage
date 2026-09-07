<?php

namespace Tests\Feature;

use App\Enums\EventFormStatus;
use App\Enums\RoleName;
use App\Models\EventForm;
use App\Models\EventFormField;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventFormPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_preview_a_draft_form(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $eventForm = EventForm::factory()->create(['status' => EventFormStatus::Draft]);
        EventFormField::factory()->for($eventForm, 'eventForm')->create(['label' => 'Full Name']);

        $this->actingAs($admin)
            ->get(route('event-forms.preview', $eventForm))
            ->assertOk()
            ->assertSee('Full Name')
            ->assertSee('Preview mode');
    }

    public function test_staff_cannot_preview_a_form(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::Staff->value);

        $eventForm = EventForm::factory()->create();

        $this->actingAs($staff)
            ->get(route('event-forms.preview', $eventForm))
            ->assertForbidden();
    }
}
