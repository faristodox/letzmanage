<?php

namespace App\Livewire\Events;

use App\Enums\EventFormStatus;
use App\Enums\EventFormType;
use App\Models\Event;
use App\Models\EventForm;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public bool $showModal = false;

    #[Validate('required|string|max:255')]
    public string $title = '';

    public ?int $confirmingDeleteId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Event::class);
    }

    public function create(): void
    {
        $this->authorize('create', Event::class);

        $this->reset(['title']);
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['title']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authorize('create', Event::class);

        $data = $this->validate();

        $event = Event::create([
            'title' => $data['title'],
            'slug' => Event::uniqueSlug($data['title']),
            'status' => EventFormStatus::Draft,
            'created_by' => auth()->id(),
        ]);

        $registrationForm = EventForm::create([
            'event_id' => $event->id,
            'type' => EventFormType::Registration,
            'status' => EventFormStatus::Draft,
            'created_by' => auth()->id(),
        ]);

        $this->redirect(route('event-forms.builder', $registrationForm), navigate: true);
    }

    public function createFeedbackForm(int $eventId): void
    {
        $event = Event::findOrFail($eventId);
        $this->authorize('create', EventForm::class);

        $feedbackForm = $event->feedbackForm ?? EventForm::create([
            'event_id' => $event->id,
            'type' => EventFormType::Feedback,
            'status' => EventFormStatus::Draft,
            'created_by' => auth()->id(),
        ]);

        $this->redirect(route('event-forms.builder', $feedbackForm), navigate: true);
    }

    public function confirmDelete(int $id): void
    {
        $event = Event::findOrFail($id);
        $this->authorize('delete', $event);

        $this->confirmingDeleteId = $id;
    }

    public function closeDeleteModal(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(): void
    {
        $event = Event::findOrFail($this->confirmingDeleteId);
        $this->authorize('delete', $event);

        if ($event->banner_path) {
            Storage::disk('public')->delete($event->banner_path);
        }

        $event->delete();
        $this->confirmingDeleteId = null;
    }

    public function render()
    {
        $events = Event::query()
            ->with([
                'registrationForm' => fn ($query) => $query->withCount('responses'),
                'feedbackForm',
            ])
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('livewire.events.index', [
            'events' => $events,
        ]);
    }
}
