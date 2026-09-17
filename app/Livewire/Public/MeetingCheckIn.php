<?php

namespace App\Livewire\Public;

use App\Enums\MeetingAttendanceMode;
use App\Models\CommitteeMember;
use App\Models\Meeting;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Livewire\Component;

/**
 * Public, unauthenticated check-in for a committee/board meeting — a
 * committee member scans the QR code or opens the check-in link, enters
 * their IC/MyKad number, and (if recognized, and invited when the meeting
 * is invitation-only) is recorded as an attendee. Confirmed check-ins feed
 * GenerateMeetingMinutesJob's Attendees section directly, replacing the
 * transcript-guessing path for meetings that use this.
 *
 * Mirrors Livewire\Public\EventCheckIn's boot()-based re-scoping pattern:
 * the meeting's organization is only resolvable from the route on the
 * initial mount, so it's re-applied on every subsequent request too.
 */
class MeetingCheckIn extends Component
{
    public ?int $organizationId = null;

    public ?int $meetingId = null;

    public string $step = 'verify';

    public string $icNumber = '';

    public ?string $matchedName = null;

    public ?string $matchedPosition = null;

    public function boot(): void
    {
        if ($this->organizationId) {
            app(CurrentOrganization::class)->set(Organization::find($this->organizationId));
        }
    }

    public function mount(Meeting $meeting): void
    {
        $this->organizationId = app(CurrentOrganization::class)->id();
        $this->meetingId = $meeting->id;
    }

    private function meeting(): Meeting
    {
        return Meeting::findOrFail($this->meetingId);
    }

    public function submit(): void
    {
        $meeting = $this->meeting();

        if ($meeting->attendance_mode === MeetingAttendanceMode::None) {
            return;
        }

        $this->validate(['icNumber' => ['required', 'string', 'max:32']]);

        $committeeMember = CommitteeMember::query()->where('ic_number', trim($this->icNumber))->first();

        $invited = $committeeMember
            && ($meeting->attendance_mode !== MeetingAttendanceMode::Invitation
                || $meeting->invitedMembers()->where('committee_members.id', $committeeMember->id)->exists());

        if (! $invited) {
            $this->step = 'not_found';

            return;
        }

        $alreadyCheckedIn = $meeting->attendees()->where('committee_members.id', $committeeMember->id)->exists();

        if (! $alreadyCheckedIn) {
            $meeting->attendees()->attach($committeeMember->id, ['checked_in_at' => now()]);
        }

        $this->matchedName = $committeeMember->name;
        $this->matchedPosition = $committeeMember->position;
        $this->step = $alreadyCheckedIn ? 'already' : 'success';
    }

    public function render()
    {
        return view('livewire.public.meeting-checkin', [
            'meeting' => $this->meeting(),
        ]);
    }
}
