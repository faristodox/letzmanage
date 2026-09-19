<?php

namespace App\Livewire\Events;

use App\Enums\EventFormStatus;
use App\Enums\EventFormType;
use App\Enums\EventType;
use App\Models\Event;
use App\Models\EventForm;
use App\Services\EventCalendarSyncService;
use App\Services\EventCreationService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public bool $showModal = false;

    public string $type = 'event';

    public string $title = '';

    public string $startDate = '';

    public string $startTime = '';

    public string $endDate = '';

    public string $endTime = '';

    public string $location = '';

    public ?int $confirmingDeleteId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Event::class);
    }

    public function create(): void
    {
        $this->authorize('create', Event::class);

        $this->reset(['type', 'title', 'startDate', 'startTime', 'endDate', 'endTime', 'location']);
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['type', 'title', 'startDate', 'startTime', 'endDate', 'endTime', 'location']);
        $this->resetValidation();
    }

    /**
     * Committee Meeting is reserved for Admin/Manager — a portfolio-scoped
     * Committee Member account can only create the public-facing Event
     * type, so they may only ever pick 'event' here regardless of what the
     * client sends.
     */
    private function allowedEventTypes(): array
    {
        return auth()->user()->portfolio_id
            ? [EventType::Event->value]
            : array_column(EventType::cases(), 'value');
    }

    public function save(EventCreationService $eventCreation): void
    {
        $this->authorize('create', Event::class);

        $data = $this->validate([
            'type' => ['required', Rule::in($this->allowedEventTypes())],
            'title' => ['required', 'string', 'max:255'],
            'startDate' => ['nullable', 'date'],
            'startTime' => ['nullable', 'date_format:H:i'],
            'endDate' => ['nullable', 'date', ...($this->startDate ? ['after_or_equal:startDate'] : [])],
            'endTime' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $registrationForm = $eventCreation->createWithRegistrationForm([
            'type' => EventType::from($data['type']),
            'title' => $data['title'],
            'start_date' => $data['startDate'] ?: null,
            'start_time' => $data['startTime'] ?: null,
            'end_date' => $data['endDate'] ?: null,
            'end_time' => $data['endTime'] ?: null,
            'location' => $data['location'] ?: null,
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

    public function delete(EventCalendarSyncService $calendarSync): void
    {
        $event = Event::findOrFail($this->confirmingDeleteId);
        $this->authorize('delete', $event);

        if ($event->banner_path) {
            Storage::disk('public')->delete($event->banner_path);
        }

        $calendarSync->syncOnDelete($event);

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
            ->when(auth()->user()->portfolio_id, fn ($query) => $query->where('portfolio_id', auth()->user()->portfolio_id))
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('livewire.events.index', [
            'events' => $events,
            'canCreateCommitteeMeeting' => auth()->user()->portfolio_id === null,
        ]);
    }
}
