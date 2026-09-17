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

    private function openCheckinMeeting(Organization $organization): Meeting
    {
        return Meeting::factory()->for($organization)->create([
            'attendance_mode' => MeetingAttendanceMode::CheckIn,
            'checkin_token' => 'test-token',
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

        $this->assertTrue($meeting->attendees()->where('committee_members.id', $committeeMember->id)->exists());
    }

    public function test_unrecognized_ic_number_shows_not_found(): void
    {
        $organization = Organization::factory()->create();
        $meeting = $this->openCheckinMeeting($organization);
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

        $this->assertSame(1, $meeting->attendees()->where('committee_members.id', $committeeMember->id)->count());
    }

    public function test_invitation_only_rejects_a_member_not_on_the_invite_list(): void
    {
        $organization = Organization::factory()->create();
        $notInvited = CommitteeMember::factory()->for($organization)->create(['ic_number' => '901231145566']);
        $meeting = Meeting::factory()->for($organization)->create([
            'attendance_mode' => MeetingAttendanceMode::Invitation,
            'checkin_token' => 'test-token',
        ]);
        app(CurrentOrganization::class)->set($organization);

        Livewire::test(MeetingCheckIn::class, ['meeting' => $meeting])
            ->set('icNumber', '901231145566')
            ->call('submit')
            ->assertSet('step', 'not_found');

        $this->assertFalse($meeting->attendees()->where('committee_members.id', $notInvited->id)->exists());
    }

    public function test_invitation_only_accepts_an_invited_member(): void
    {
        $organization = Organization::factory()->create();
        $invited = CommitteeMember::factory()->for($organization)->create(['ic_number' => '901231145566']);
        $meeting = Meeting::factory()->for($organization)->create([
            'attendance_mode' => MeetingAttendanceMode::Invitation,
            'checkin_token' => 'test-token',
        ]);
        $meeting->invitedMembers()->attach($invited->id);
        app(CurrentOrganization::class)->set($organization);

        Livewire::test(MeetingCheckIn::class, ['meeting' => $meeting])
            ->set('icNumber', '901231145566')
            ->call('submit')
            ->assertSet('step', 'success');

        $this->assertTrue($meeting->attendees()->where('committee_members.id', $invited->id)->exists());
    }
}
