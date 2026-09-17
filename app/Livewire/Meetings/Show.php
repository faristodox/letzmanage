<?php

namespace App\Livewire\Meetings;

use App\Enums\MeetingAttendanceMode;
use App\Models\Meeting;
use Illuminate\Support\Str;
use Livewire\Component;

class Show extends Component
{
    public Meeting $meeting;

    public string $language = 'en';

    public string $attendanceMode = 'none';

    /** @var array<int> */
    public array $invitedMemberIds = [];

    public function mount(Meeting $meeting): void
    {
        $this->authorize('view', $meeting);

        $this->meeting = $meeting;
        $this->attendanceMode = $meeting->attendance_mode->value;
        $this->invitedMemberIds = $meeting->invitedMembers()->pluck('committee_members.id')->all();
    }

    public function setLanguage(string $language): void
    {
        $this->language = $language === 'ms' ? 'ms' : 'en';
    }

    public function saveAttendanceSettings(): void
    {
        $this->authorize('update', $this->meeting);

        $mode = MeetingAttendanceMode::tryFrom($this->attendanceMode) ?? MeetingAttendanceMode::None;

        $this->meeting->update([
            'attendance_mode' => $mode,
            'checkin_token' => $mode !== MeetingAttendanceMode::None
                ? ($this->meeting->checkin_token ?: Str::random(32))
                : $this->meeting->checkin_token,
        ]);

        $this->meeting->invitedMembers()->sync(
            $mode === MeetingAttendanceMode::Invitation ? $this->invitedMemberIds : []
        );
    }

    public function currentMinutes(): ?string
    {
        if ($this->language === 'ms' && $this->meeting->minutes_ms) {
            return $this->meeting->minutes_ms;
        }

        return $this->meeting->minutes;
    }

    public function delete()
    {
        $this->authorize('delete', $this->meeting);

        $this->meeting->delete();

        return $this->redirect(route('meetings.index'), navigate: true);
    }

    public function downloadTranscript()
    {
        $this->authorize('view', $this->meeting);

        return response()->streamDownload(
            fn () => print ((string) $this->meeting->transcript),
            "{$this->meeting->title}-transcript.txt",
        );
    }

    public function downloadMinutes()
    {
        $this->authorize('view', $this->meeting);

        $suffix = $this->language === 'ms' && $this->meeting->minutes_ms ? '-minutes-ms' : '-minutes';

        return response()->streamDownload(
            fn () => print ((string) $this->currentMinutes()),
            "{$this->meeting->title}{$suffix}.txt",
        );
    }

    public function render()
    {
        // Stop wire:poll once the pipeline reaches a terminal state — no
        // point polling a Meeting that's already Ready or Failed.
        $this->meeting->refresh();

        return view('livewire.meetings.show', [
            'committeeMembers' => auth()->user()->organization->committeeMembers()->orderBy('name')->get(),
            'attendees' => $this->meeting->attendees()->orderByDesc('meeting_attendances.checked_in_at')->get(),
        ]);
    }
}
