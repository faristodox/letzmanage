<?php

namespace Tests\Feature\Livewire;

use App\Enums\EventFormStatus;
use App\Enums\RoleName;
use App\Livewire\Events\Index;
use App\Models\Event;
use App\Models\EventForm;
use App\Models\EventFormResponse;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EventsIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_create_an_event_and_is_redirected_to_the_registration_form_builder(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('create')
            ->set('title', 'Annual Dinner 2026')
            ->call('save')
            ->assertHasNoErrors();

        $event = Event::where('title', 'Annual Dinner 2026')->first();
        $this->assertNotNull($event);
        $this->assertSame('annual-dinner-2026', $event->slug);
        $this->assertSame($admin->id, $event->created_by);

        $registrationForm = $event->registrationForm;
        $this->assertNotNull($registrationForm);
        $this->assertSame(EventFormStatus::Draft, $registrationForm->status);
        $this->assertSame($admin->id, $registrationForm->created_by);
    }

    public function test_staff_cannot_create_an_event(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::Staff->value);

        Livewire::actingAs($staff)
            ->test(Index::class)
            ->assertForbidden();
    }

    public function test_deleting_an_event_also_deletes_its_registration_form_and_responses(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $event = Event::factory()->create();
        $registrationForm = EventForm::factory()->for($event)->create();
        EventFormResponse::factory()->for($registrationForm, 'eventForm')->create();

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('confirmDelete', $event->id)
            ->call('delete');

        $this->assertNull(Event::find($event->id));
        $this->assertNull(EventForm::find($registrationForm->id));
        $this->assertSame(0, EventFormResponse::count());
    }

    public function test_deleting_an_event_also_deletes_its_banner_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('events/banner.jpg', 'fake-image-content');

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $event = Event::factory()->create(['banner_path' => 'events/banner.jpg']);
        EventForm::factory()->for($event)->create();

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('confirmDelete', $event->id)
            ->call('delete');

        Storage::disk('public')->assertMissing('events/banner.jpg');
    }

    public function test_actions_column_links_to_registration_feedback_financial_and_report(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $event = Event::factory()->create();
        $registrationForm = EventForm::factory()->for($event)->create();

        $response = $this->actingAs($admin)->get(route('events.index'));

        $response->assertOk();
        $response->assertSee(route('event-forms.builder', $registrationForm), false);
        $response->assertSee(route('event-forms.builder', $registrationForm).'#form-settings', false);
        $response->assertSee(route('event-forms.builder', $registrationForm).'#feedback-survey', false);
        $response->assertSee(route('events.finances', $event), false);
        $response->assertSee(route('events.report', $event), false);
    }

    public function test_feedback_action_links_directly_to_the_feedback_form_once_one_exists(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $event = Event::factory()->create();
        EventForm::factory()->for($event)->create();
        $feedbackForm = EventForm::factory()->for($event)->feedback()->create();

        $this->actingAs($admin)
            ->get(route('events.index'))
            ->assertOk()
            ->assertSee(route('event-forms.builder', $feedbackForm), false);
    }
}
