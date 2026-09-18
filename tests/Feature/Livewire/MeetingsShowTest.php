<?php

namespace Tests\Feature\Livewire;

use App\Enums\EventType;
use App\Enums\RoleName;
use App\Livewire\Meetings\Show;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MeetingsShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(Organization $organization): User
    {
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $admin->assignRole(RoleName::Admin->value);

        return $admin;
    }

    public function test_staff_without_permission_is_forbidden(): void
    {
        $organization = Organization::factory()->create();
        $staff = User::factory()->create(['organization_id' => $organization->id]);
        $staff->assignRole(RoleName::Staff->value);
        $meeting = Meeting::factory()->for($organization)->create();

        Livewire::actingAs($staff)
            ->test(Show::class, ['meeting' => $meeting])
            ->assertForbidden();
    }

    public function test_shows_transcript_and_minutes_when_ready(): void
    {
        $organization = Organization::factory()->create();
        $meeting = Meeting::factory()->for($organization)->create([
            'title' => 'Usrah Session',
            'transcript' => 'Faris: hello everyone.',
            'minutes' => 'Title: Usrah Session',
        ]);

        Livewire::actingAs($this->admin($organization))
            ->test(Show::class, ['meeting' => $meeting])
            ->assertSee('Usrah Session')
            ->assertSee('Faris: hello everyone.')
            ->assertSee('Title: Usrah Session');
    }

    public function test_shows_failure_reason_when_failed(): void
    {
        $organization = Organization::factory()->create();
        $meeting = Meeting::factory()->failed()->for($organization)->create([
            'failure_reason' => 'Transcription timed out after 2 hours.',
        ]);

        Livewire::actingAs($this->admin($organization))
            ->test(Show::class, ['meeting' => $meeting])
            ->assertSee('Transcription timed out after 2 hours.');
    }

    public function test_admin_can_delete_a_meeting(): void
    {
        $organization = Organization::factory()->create();
        $meeting = Meeting::factory()->for($organization)->create();

        Livewire::actingAs($this->admin($organization))
            ->test(Show::class, ['meeting' => $meeting])
            ->call('delete')
            ->assertRedirect(route('meetings.index'));

        $this->assertDatabaseCount('meetings', 0);
    }

    public function test_can_download_the_transcript(): void
    {
        $organization = Organization::factory()->create();
        $meeting = Meeting::factory()->for($organization)->create([
            'title' => 'Usrah Session',
            'transcript' => 'Faris: hello everyone.',
        ]);

        Livewire::actingAs($this->admin($organization))
            ->test(Show::class, ['meeting' => $meeting])
            ->call('downloadTranscript')
            ->assertFileDownloaded('Usrah Session-transcript.txt');
    }

    public function test_points_to_the_linked_committee_meeting_events_attendance_panel(): void
    {
        $organization = Organization::factory()->create();
        $event = Event::factory()->for($organization)->create(['type' => EventType::CommitteeMeeting]);
        $meeting = Meeting::factory()->for($organization)->create(['event_id' => $event->id]);

        Livewire::actingAs($this->admin($organization))
            ->test(Show::class, ['meeting' => $meeting])
            ->assertSee('Attendance for this meeting is tracked on its linked event');
    }

    public function test_does_not_mention_attendance_tracking_for_a_plain_linked_event(): void
    {
        $organization = Organization::factory()->create();
        $event = Event::factory()->for($organization)->create(['type' => EventType::Event]);
        $meeting = Meeting::factory()->for($organization)->create(['event_id' => $event->id]);

        Livewire::actingAs($this->admin($organization))
            ->test(Show::class, ['meeting' => $meeting])
            ->assertDontSee('Attendance for this meeting is tracked on its linked event');
    }

    public function test_admin_can_edit_the_minutes_text(): void
    {
        $organization = Organization::factory()->create();
        $meeting = Meeting::factory()->for($organization)->create(['minutes' => 'Original minutes text']);

        Livewire::actingAs($this->admin($organization))
            ->test(Show::class, ['meeting' => $meeting])
            ->call('startEditingMinutes')
            ->assertSet('editMinutesText', 'Original minutes text')
            ->set('editMinutesText', 'Corrected minutes text')
            ->call('saveMinutesEdits')
            ->assertSet('editingMinutes', false);

        $this->assertSame('Corrected minutes text', $meeting->refresh()->minutes);
    }

    public function test_admin_can_add_and_save_agenda_items(): void
    {
        $organization = Organization::factory()->create();
        $meeting = Meeting::factory()->for($organization)->create(['minutes' => 'Some minutes']);

        Livewire::actingAs($this->admin($organization))
            ->test(Show::class, ['meeting' => $meeting])
            ->call('startEditingMinutes')
            ->call('addAgendaItem')
            ->set('editAgendaItems.0.topic', "Ta'aruf")
            ->set('editAgendaItems.0.subPoints', "Sesi perkenalan\nPerlantikan ahli baharu")
            ->set('editAgendaItems.0.actionBy', 'Makluman')
            ->call('saveMinutesEdits')
            ->assertHasNoErrors();

        $agendaItems = $meeting->refresh()->agenda_items['agenda_items'];
        $this->assertSame("Ta'aruf", $agendaItems[0]['topic']);
        $this->assertSame(['Sesi perkenalan', 'Perlantikan ahli baharu'], $agendaItems[0]['sub_points']);
        $this->assertSame('Makluman', $agendaItems[0]['action_by']);
    }

    public function test_removing_an_agenda_item_before_saving_excludes_it(): void
    {
        $organization = Organization::factory()->create();
        $meeting = Meeting::factory()->for($organization)->create(['minutes' => 'Some minutes']);

        Livewire::actingAs($this->admin($organization))
            ->test(Show::class, ['meeting' => $meeting])
            ->call('startEditingMinutes')
            ->call('addAgendaItem')
            ->set('editAgendaItems.0.topic', 'Keep me')
            ->call('addAgendaItem')
            ->set('editAgendaItems.1.topic', 'Remove me')
            ->call('removeAgendaItem', 1)
            ->call('saveMinutesEdits');

        $agendaItems = $meeting->refresh()->agenda_items['agenda_items'];
        $this->assertCount(1, $agendaItems);
        $this->assertSame('Keep me', $agendaItems[0]['topic']);
    }

    public function test_blank_topic_agenda_items_are_dropped_on_save(): void
    {
        $organization = Organization::factory()->create();
        $meeting = Meeting::factory()->for($organization)->create(['minutes' => 'Some minutes']);

        Livewire::actingAs($this->admin($organization))
            ->test(Show::class, ['meeting' => $meeting])
            ->call('startEditingMinutes')
            ->call('addAgendaItem')
            ->call('saveMinutesEdits');

        $this->assertSame([], $meeting->refresh()->agenda_items['agenda_items']);
    }

    public function test_editing_preserves_the_existing_fallback_attendee_list(): void
    {
        $organization = Organization::factory()->create();
        $meeting = Meeting::factory()->for($organization)->create([
            'minutes' => 'Some minutes',
            'agenda_items' => ['attendees' => [['name' => 'Guessed Person', 'position' => 'Member']], 'agenda_items' => []],
        ]);

        Livewire::actingAs($this->admin($organization))
            ->test(Show::class, ['meeting' => $meeting])
            ->call('startEditingMinutes')
            ->call('addAgendaItem')
            ->set('editAgendaItems.0.topic', 'New topic')
            ->call('saveMinutesEdits');

        $this->assertSame('Guessed Person', $meeting->refresh()->agenda_items['attendees'][0]['name']);
    }

    public function test_admin_can_override_prepared_by_and_confirmed_by(): void
    {
        $organization = Organization::factory()->create();
        $meeting = Meeting::factory()->for($organization)->create(['minutes' => 'Some minutes']);

        Livewire::actingAs($this->admin($organization))
            ->test(Show::class, ['meeting' => $meeting])
            ->call('startEditingMinutes')
            ->set('editPreparedByName', 'Custom Preparer')
            ->set('editPreparedByPosition', 'Custom Position')
            ->set('editConfirmedByName', 'Custom Confirmer')
            ->set('editConfirmedByPosition', 'Custom Role')
            ->call('saveMinutesEdits');

        $meeting->refresh();
        $this->assertSame('Custom Preparer', $meeting->prepared_by_name);
        $this->assertSame('Custom Position', $meeting->prepared_by_position);
        $this->assertSame('Custom Confirmer', $meeting->confirmed_by_name);
        $this->assertSame('Custom Role', $meeting->confirmed_by_position);
    }
}
