<?php

namespace Tests\Feature\Livewire\Public;

use App\Enums\MeetingAttendanceMode;
use App\Livewire\Public\MeetingCheckIn;
use App\Models\CommitteeMember;
use App\Models\Meeting;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MeetingCheckInTest extends TestCase
{
    use RefreshDatabase;

    private function openCheckinMeeting(Organization $organization, bool $allowNewRegistration = false): Meeting
    {
        return Meeting::factory()->for($organization)->create([
            'attendance_mode' => MeetingAttendanceMode::CheckIn,
            'checkin_token' => 'test-token',
            'allow_new_registration' => $allowNewRegistration,
        ]);
    }

    public function test_recognized_ic_number_checks_the_member_in(): void
    {
        $organization = Organization::factory()->create();
        $committeeMember = CommitteeMember::factory()->for($organization)->create([
            'name' => 'Ahmad Zaki', 'position' => 'President', 'ic_number' => '901231145566',
        ]);
        $meeting = $this->openCheckinMeeting($organization);
        app(CurrentOrganization::class)->set($organization);

        Livewire::test(MeetingCheckIn::class, ['meeting' => $meeting])
            ->set('icNumber', '901231145566')
            ->call('submit')
            ->assertSet('step', 'success')
            ->assertSet('matchedName', 'Ahmad Zaki')
            ->assertSet('matchedPosition', 'President');

        $this->assertTrue($meeting->attendees()->where('committee_member_id', $committeeMember->id)->exists());
    }

    public function test_unrecognized_ic_number_shows_not_found_when_registration_is_disabled(): void
    {
        $organization = Organization::factory()->create();
        $meeting = $this->openCheckinMeeting($organization, allowNewRegistration: false);
        app(CurrentOrganization::class)->set($organization);

        Livewire::test(MeetingCheckIn::class, ['meeting' => $meeting])
            ->set('icNumber', '000000000000')
            ->call('submit')
            ->assertSet('step', 'not_found');

        $this->assertSame(0, $meeting->attendees()->count());
    }

    public function test_checking_in_twice_does_not_duplicate_and_shows_already_state(): void
    {
        $organization = Organization::factory()->create();
        $committeeMember = CommitteeMember::factory()->for($organization)->create(['ic_number' => '901231145566']);
        $meeting = $this->openCheckinMeeting($organization);
        app(CurrentOrganization::class)->set($organization);

        Livewire::test(MeetingCheckIn::class, ['meeting' => $meeting])
            ->set('icNumber', '901231145566')
            ->call('submit')
            ->assertSet('step', 'success');

        Livewire::test(MeetingCheckIn::class, ['meeting' => $meeting])
            ->set('icNumber', '901231145566')
            ->call('submit')
            ->assertSet('step', 'already');

        $this->assertSame(1, $meeting->attendees()->where('committee_member_id', $committeeMember->id)->count());
    }

    public function test_unrecognized_ic_with_registration_enabled_prompts_for_registration(): void
    {
        $organization = Organization::factory()->create();
        $meeting = $this->openCheckinMeeting($organization, allowNewRegistration: true);
        app(CurrentOrganization::class)->set($organization);

        Livewire::test(MeetingCheckIn::class, ['meeting' => $meeting])
            ->set('icNumber', '000000000000')
            ->call('submit')
            ->assertSet('step', 'register');

        $this->assertSame(0, $meeting->attendees()->count());
    }

    public function test_completing_registration_records_a_guest_attendee(): void
    {
        $organization = Organization::factory()->create();
        $meeting = $this->openCheckinMeeting($organization, allowNewRegistration: true);
        app(CurrentOrganization::class)->set($organization);

        Livewire::test(MeetingCheckIn::class, ['meeting' => $meeting])
            ->set('icNumber', '000000000000')
            ->call('submit')
            ->assertSet('step', 'register')
            ->set('guestName', 'Guest Speaker')
            ->set('guestPosition', 'Invited Guest')
            ->call('submitRegistration')
            ->assertSet('step', 'success')
            ->assertSet('matchedName', 'Guest Speaker')
            ->assertSet('matchedPosition', 'Invited Guest');

        $attendee = $meeting->attendees()->first();
        $this->assertNotNull($attendee);
        $this->assertNull($attendee->committee_member_id);
        $this->assertSame('Guest Speaker', $attendee->guest_name);
        $this->assertSame('Invited Guest', $attendee->guest_position);
        $this->assertSame('000000000000', $attendee->guest_ic_number);
    }

    public function test_registration_requires_a_name_but_not_a_position(): void
    {
        $organization = Organization::factory()->create();
        $meeting = $this->openCheckinMeeting($organization, allowNewRegistration: true);
        app(CurrentOrganization::class)->set($organization);

        Livewire::test(MeetingCheckIn::class, ['meeting' => $meeting])
            ->set('icNumber', '000000000000')
            ->call('submit')
            ->set('guestName', '')
            ->call('submitRegistration')
            ->assertHasErrors(['guestName']);

        $this->assertSame(0, $meeting->attendees()->count());
    }
}
