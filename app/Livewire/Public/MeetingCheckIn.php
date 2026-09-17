<?php

namespace App\Livewire\Public;

use App\Enums\MeetingAttendanceMode;
use App\Models\CommitteeMember;
use App\Models\Meeting;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Illuminate\Database\QueryException;
use Livewire\Component;

/**
 * Public, unauthenticated check-in for a committee/board meeting — a
 * committee member scans the QR code or opens the check-in link and enters
 * their IC/MyKad number. If recognized against the Committee Members
 * roster, they're recorded as an attendee. If not, and the meeting allows
 * new registration, they can instead register on the spot with just a name
 * and (optional) position — recorded as a guest attendee, not added to the
 * permanent roster. Confirmed check-ins feed GenerateMeetingMinutesJob's
 * Attendees section directly, replacing the transcript-guessing path for
 * meetings that use this.
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

    public string $guestName = '';

    public string $guestPosition = '';

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

        if (! $committeeMember) {
            $this->step = $meeting->allow_new_registration ? 'register' : 'not_found';

            return;
        }

        $alreadyCheckedIn = $meeting->attendees()->where('committee_member_id', $committeeMember->id)->exists();

        if (! $alreadyCheckedIn) {
            try {
                $meeting->attendees()->create([
                    'committee_member_id' => $committeeMember->id,
                    'checked_in_at' => now(),
                ]);
            } catch (QueryException) {
                // Unique constraint: someone else checked this member in between the check and the create.
            }
        }

        $this->matchedName = $committeeMember->name;
        $this->matchedPosition = $committeeMember->position;
        $this->step = $alreadyCheckedIn ? 'already' : 'success';
    }

    public function submitRegistration(): void
    {
        $meeting = $this->meeting();

        if (! $meeting->allow_new_registration) {
            return;
        }

        $this->validate([
            'guestName' => ['required', 'string', 'max:255'],
            'guestPosition' => ['nullable', 'string', 'max:255'],
        ]);

        $meeting->attendees()->create([
            'guest_name' => $this->guestName,
            'guest_position' => $this->guestPosition ?: null,
            'guest_ic_number' => trim($this->icNumber) ?: null,
            'checked_in_at' => now(),
        ]);

        $this->matchedName = $this->guestName;
        $this->matchedPosition = $this->guestPosition ?: null;
        $this->step = 'success';
    }

    public function render()
    {
        return view('livewire.public.meeting-checkin', [
            'meeting' => $this->meeting(),
        ]);
    }
}
