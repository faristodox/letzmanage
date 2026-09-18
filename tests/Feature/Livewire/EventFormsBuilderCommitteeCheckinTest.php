<?php

namespace Tests\Feature\Livewire;

use App\Enums\EventType;
use App\Enums\RoleName;
use App\Livewire\EventForms\Builder;
use App\Models\CommitteeMember;
use App\Models\Event;
use App\Models\EventForm;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventFormsBuilderCommitteeCheckinTest extends TestCase
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

    private function committeeMeetingForm(): EventForm
    {
        $event = Event::factory()->create(['type' => EventType::CommitteeMeeting]);

        return EventForm::factory()->for($event)->create();
    }

    public function test_admin_can_enable_committee_checkin(): void
    {
        $eventForm = $this->committeeMeetingForm();

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['eventForm' => $eventForm])
            ->set('committeeCheckinEnabled', true)
            ->call('saveCommitteeCheckinSettings')
            ->assertHasNoErrors();

        $event = $eventForm->event->fresh();
        $this->assertTrue($event->checkin_enabled);
        $this->assertNotNull($event->checkin_token);
    }

    public function test_admin_can_enable_allow_new_registration(): void
    {
        $eventForm = $this->committeeMeetingForm();

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['eventForm' => $eventForm])
            ->set('committeeCheckinEnabled', true)
            ->set('committeeAllowNewRegistration', true)
            ->call('saveCommitteeCheckinSettings');

        $event = $eventForm->event->fresh();
        $this->assertTrue($event->allow_new_registration);
    }

    public function test_registration_form_specific_sections_are_hidden_for_committee_meetings(): void
    {
        $eventForm = $this->committeeMeetingForm();

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['eventForm' => $eventForm])
            ->assertDontSee('Registration Form Settings')
            ->assertDontSee('Add Field')
            ->assertSee('Attendance');
    }

    public function test_a_plain_events_builder_still_shows_registration_sections(): void
    {
        $event = Event::factory()->create(['type' => EventType::Event]);
        $eventForm = EventForm::factory()->for($event)->create();

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['eventForm' => $eventForm])
            ->assertSee('Registration Form Settings')
            ->assertSee('Event Day Check-in')
            ->assertDontSee('Let committee/board members check in themselves');
    }

    public function test_checked_in_committee_members_are_listed_once_checkin_is_enabled(): void
    {
        $organization = Organization::factory()->create();
        $event = Event::factory()->for($organization)->create(['type' => EventType::CommitteeMeeting, 'checkin_enabled' => true, 'checkin_token' => 'test-token']);
        $eventForm = EventForm::factory()->for($event)->for($organization)->create();
        $committeeMember = CommitteeMember::factory()->for($organization)->create(['name' => 'Ahmad Zaki', 'position' => 'President']);
        $event->attendees()->create(['committee_member_id' => $committeeMember->id, 'checked_in_at' => now()]);

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['eventForm' => $eventForm])
            ->assertSee('Ahmad Zaki')
            ->assertSee('President');
    }
}
