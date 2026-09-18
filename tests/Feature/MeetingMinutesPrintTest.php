<?php

namespace Tests\Feature;

use App\Enums\EventType;
use App\Enums\RoleName;
use App\Models\CommitteeMember;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingMinutesPrintTest extends TestCase
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

        $this->actingAs($staff)->get(route('meetings.print', $meeting))->assertForbidden();
    }

    public function test_renders_confirmed_attendees_from_the_linked_committee_meeting_event(): void
    {
        $organization = Organization::factory()->create();
        $event = Event::factory()->for($organization)->create(['type' => EventType::CommitteeMeeting]);
        $committeeMember = CommitteeMember::factory()->for($organization)->create(['name' => 'Ahmad Zaki', 'position' => 'President']);
        $event->attendees()->create(['committee_member_id' => $committeeMember->id, 'checked_in_at' => now()]);
        $meeting = Meeting::factory()->for($organization)->create(['event_id' => $event->id]);

        $response = $this->actingAs($this->admin($organization))->get(route('meetings.print', $meeting));

        $response->assertOk();
        $response->assertSee('Ahmad Zaki');
        $response->assertSee('President');
        $response->assertSee('Confirmed via check-in.', false);
    }

    public function test_falls_back_to_the_ai_extracted_attendees_when_no_confirmed_checkin(): void
    {
        $organization = Organization::factory()->create();
        $meeting = Meeting::factory()->for($organization)->create([
            'agenda_items' => [
                'attendees' => [['name' => 'Guessed Person', 'position' => 'Member']],
                'agenda_items' => [],
            ],
        ]);

        $response = $this->actingAs($this->admin($organization))->get(route('meetings.print', $meeting));

        $response->assertOk();
        $response->assertSee('Guessed Person');
        $response->assertSee('Identified from the recording', false);
    }

    public function test_renders_structured_agenda_items(): void
    {
        $organization = Organization::factory()->create();
        $meeting = Meeting::factory()->for($organization)->create([
            'agenda_items' => [
                'attendees' => [],
                'agenda_items' => [[
                    'topic' => "Ta'aruf",
                    'sub_points' => ['Sesi perkenalan ahli JKK.'],
                    'action_by' => 'Makluman',
                    'notes' => '',
                ]],
            ],
        ]);

        $response = $this->actingAs($this->admin($organization))->get(route('meetings.print', $meeting));

        $response->assertOk();
        $response->assertSee("Ta'aruf");
        $response->assertSee('Sesi perkenalan ahli JKK.');
        $response->assertSee('Makluman');
    }

    public function test_uses_the_malay_agenda_data_and_labels_when_requested(): void
    {
        $organization = Organization::factory()->create();
        $meeting = Meeting::factory()->for($organization)->create([
            'agenda_items' => ['attendees' => [], 'agenda_items' => [['topic' => 'Introduction', 'sub_points' => [], 'action_by' => '', 'notes' => '']]],
            'agenda_items_ms' => ['attendees' => [], 'agenda_items' => [['topic' => 'Perkenalan', 'sub_points' => [], 'action_by' => '', 'notes' => '']]],
        ]);

        $response = $this->actingAs($this->admin($organization))->get(route('meetings.print', ['meeting' => $meeting, 'lang' => 'ms']));

        $response->assertOk();
        $response->assertSee('Perkenalan');
        $response->assertDontSee('Introduction');
        $response->assertSee('Kehadiran');
    }

    public function test_prepared_by_defaults_to_the_meetings_creator(): void
    {
        $organization = Organization::factory()->create();
        $creator = User::factory()->create(['organization_id' => $organization->id, 'name' => 'Muhamad Faris']);
        CommitteeMember::factory()->for($organization)->create(['name' => 'Muhamad Faris', 'position' => 'Penolong Setiausaha']);
        $meeting = Meeting::factory()->for($organization)->create(['created_by' => $creator->id]);

        $response = $this->actingAs($this->admin($organization))->get(route('meetings.print', $meeting));

        $response->assertOk();
        $response->assertSee('Muhamad Faris');
        $response->assertSee('Penolong Setiausaha');
    }

    public function test_confirmed_by_defaults_to_the_committee_secretary(): void
    {
        $organization = Organization::factory()->create();
        CommitteeMember::factory()->for($organization)->create(['name' => 'Muhammad Adam', 'position' => 'Setiausaha']);
        $meeting = Meeting::factory()->for($organization)->create();

        $response = $this->actingAs($this->admin($organization))->get(route('meetings.print', $meeting));

        $response->assertOk();
        $response->assertSee('Muhammad Adam');
        $response->assertSee('Setiausaha');
    }

    public function test_manual_signoff_overrides_take_priority_over_the_computed_defaults(): void
    {
        $organization = Organization::factory()->create();
        CommitteeMember::factory()->for($organization)->create(['name' => 'Muhammad Adam', 'position' => 'Setiausaha']);
        $meeting = Meeting::factory()->for($organization)->create([
            'prepared_by_name' => 'Manually Set Preparer',
            'prepared_by_position' => 'Custom Role',
            'confirmed_by_name' => 'Manually Set Confirmer',
            'confirmed_by_position' => 'Custom Confirming Role',
        ]);

        $response = $this->actingAs($this->admin($organization))->get(route('meetings.print', $meeting));

        $response->assertOk();
        $response->assertSee('Manually Set Preparer');
        $response->assertSee('Manually Set Confirmer');
        $response->assertDontSee('Muhammad Adam');
    }
}
