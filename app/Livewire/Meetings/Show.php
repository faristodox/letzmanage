<?php

namespace App\Livewire\Meetings;

use App\Models\Meeting;
use Livewire\Component;

class Show extends Component
{
    public Meeting $meeting;

    public function mount(Meeting $meeting): void
    {
        $this->authorize('view', $meeting);

        $this->meeting = $meeting;
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

        return response()->streamDownload(
            fn () => print ((string) $this->meeting->minutes),
            "{$this->meeting->title}-minutes.txt",
        );
    }

    public function render()
    {
        // Stop wire:poll once the pipeline reaches a terminal state — no
        // point polling a Meeting that's already Ready or Failed.
        $this->meeting->refresh();

        return view('livewire.meetings.show');
    }
}
