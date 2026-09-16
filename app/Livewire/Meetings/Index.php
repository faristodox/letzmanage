<?php

namespace App\Livewire\Meetings;

use App\Exceptions\MeetingNotConfiguredException;
use App\Models\Event;
use App\Models\Meeting;
use App\Services\MeetingService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use WithFileUploads, WithPagination;

    public $file = null;

    public bool $showModal = false;

    public string $activeTab = 'record';

    public string $title = '';

    public ?int $eventId = null;

    public ?int $recordedDurationSeconds = null;

    public ?string $uploadError = null;

    public function mount(?int $event = null): void
    {
        $this->authorize('viewAny', Meeting::class);

        $this->eventId = $event;
    }

    public function create(): void
    {
        $this->authorize('create', Meeting::class);

        $this->reset(['title', 'file', 'recordedDurationSeconds', 'uploadError']);
        $this->resetValidation();
        $this->activeTab = 'record';
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['title', 'file', 'recordedDurationSeconds', 'uploadError']);
        $this->resetValidation();
    }

    /**
     * Named save(), not upload() — "upload" is a reserved Livewire
     * client-side method name (see App\Livewire\Archive\Index's identical
     * note); reusing it silently breaks wire:model="file"/$wire.upload().
     */
    public function save(MeetingService $meetings): void
    {
        $this->authorize('create', Meeting::class);

        $data = $this->validate([
            'file' => ['required', 'file', 'mimes:webm,mp3,wav,ogg,flac', 'max:245760'],
            'title' => ['required', 'string', 'max:255'],
        ]);

        $this->uploadError = null;

        try {
            $meeting = $meetings->createFromUpload(
                auth()->user()->organization,
                auth()->user(),
                $this->file,
                $this->eventId,
                $data['title'],
                $this->recordedDurationSeconds,
            );
        } catch (MeetingNotConfiguredException $e) {
            $this->uploadError = $e->getMessage();

            return;
        }

        $this->redirect(route('meetings.show', $meeting), navigate: true);
    }

    public function clearEventFilter(): void
    {
        $this->eventId = null;
    }

    public function render()
    {
        $meetings = Meeting::query()
            ->with(['event', 'creator'])
            ->when($this->eventId, fn ($query) => $query->where('event_id', $this->eventId))
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('livewire.meetings.index', [
            'meetings' => $meetings,
            'events' => Event::query()->orderByDesc('created_at')->limit(50)->get(['id', 'title']),
            'filteredEvent' => $this->eventId ? Event::find($this->eventId) : null,
        ]);
    }
}
