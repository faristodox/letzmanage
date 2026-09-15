<?php

namespace App\Livewire\Bookings;

use App\Enums\BookingStatus;
use App\Enums\CalendarFilterType;
use App\Enums\OfficeSpaceStatus;
use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use App\Models\Event;
use App\Models\OfficeSpace;
use App\Services\BookingService;
use App\Services\EventCreationService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Component;

class Calendar extends Component
{
    public ?int $space_id = null;

    public string $month;

    public string $type = 'all';

    public bool $canViewEvents = false;

    public bool $showModal = false;

    public string $modalTab = 'event';

    public string $date = '';

    public string $title = '';

    public string $start_time = '';

    public string $end_time = '';

    public string $eventTitle = '';

    public string $eventStartDate = '';

    public string $eventStartTime = '';

    public string $eventEndDate = '';

    public string $eventEndTime = '';

    public string $eventLocation = '';

    public ?int $viewBookingId = null;

    public ?int $viewEventId = null;

    public bool $confirmingApprove = false;

    public bool $confirmingReject = false;

    public string $approveNote = '';

    public string $rejectReason = '';

    public ?string $viewErrorMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->authorize('create', Booking::class);

        $this->month = now()->format('Y-m');
        $this->canViewEvents = auth()->user()->can('viewAny', Event::class);

        $firstSpace = OfficeSpace::query()
            ->visibleTo(auth()->user())
            ->where('status', OfficeSpaceStatus::Active)
            ->orderBy('name')
            ->first();

        $this->space_id = $firstSpace?->id;
    }

    public function previousMonth(): void
    {
        $this->month = CarbonImmutable::parse($this->month.'-01')->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = CarbonImmutable::parse($this->month.'-01')->addMonth()->format('Y-m');
    }

    public function openCreate(string $date): void
    {
        $this->authorize('create', Booking::class);

        $this->date = $date;
        $this->title = '';
        $this->start_time = '09:00';
        $this->end_time = '10:00';
        $this->reset(['eventTitle', 'eventStartTime', 'eventEndDate', 'eventEndTime', 'eventLocation']);
        $this->eventStartDate = $date;
        $this->errorMessage = null;
        $this->modalTab = $this->canViewEvents ? 'event' : 'booking';
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['title', 'start_time', 'end_time']);
        $this->reset(['eventTitle', 'eventStartDate', 'eventStartTime', 'eventEndDate', 'eventEndTime', 'eventLocation']);
        $this->resetValidation();
        $this->errorMessage = null;
    }

    public function viewBooking(int $id): void
    {
        $this->viewEventId = null;
        $this->viewBookingId = $id;
        $this->resetViewState();
    }

    public function viewEvent(int $id): void
    {
        $this->viewBookingId = null;
        $this->viewEventId = $id;
        $this->resetViewState();
    }

    public function closeView(): void
    {
        $this->viewBookingId = null;
        $this->viewEventId = null;
        $this->resetViewState();
    }

    public function confirmApprove(): void
    {
        $booking = Booking::findOrFail($this->viewBookingId);
        $this->authorize('approve', $booking);

        $this->confirmingApprove = true;
        $this->confirmingReject = false;
        $this->approveNote = '';
        $this->viewErrorMessage = null;
    }

    public function confirmReject(): void
    {
        $booking = Booking::findOrFail($this->viewBookingId);
        $this->authorize('reject', $booking);

        $this->confirmingReject = true;
        $this->confirmingApprove = false;
        $this->rejectReason = '';
        $this->viewErrorMessage = null;
    }

    public function cancelConfirm(): void
    {
        $this->confirmingApprove = false;
        $this->confirmingReject = false;
    }

    public function approveBooking(BookingService $bookingService): void
    {
        $booking = Booking::findOrFail($this->viewBookingId);
        $this->authorize('approve', $booking);

        try {
            $bookingService->approve($booking, auth()->user(), $this->approveNote ?: null);
        } catch (BookingConflictException $e) {
            $this->viewErrorMessage = $e->getMessage();

            return;
        }

        $this->closeView();
        session()->flash('status', __('Booking approved.'));
    }

    public function rejectBooking(BookingService $bookingService): void
    {
        $booking = Booking::findOrFail($this->viewBookingId);
        $this->authorize('reject', $booking);

        $bookingService->reject($booking, auth()->user(), $this->rejectReason ?: null);

        $this->closeView();
        session()->flash('status', __('Booking rejected.'));
    }

    private function resetViewState(): void
    {
        $this->confirmingApprove = false;
        $this->confirmingReject = false;
        $this->approveNote = '';
        $this->rejectReason = '';
        $this->viewErrorMessage = null;
    }

    public function save(BookingService $bookingService): void
    {
        $this->authorize('create', Booking::class);

        $this->validate([
            'space_id' => ['required', 'integer', 'exists:office_spaces,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        $space = OfficeSpace::findOrFail($this->space_id);

        $start = Carbon::parse("{$this->date} {$this->start_time}");
        $end = Carbon::parse("{$this->date} {$this->end_time}");

        if ($start->isPast()) {
            $this->addError('start_time', __('The start time must be in the future.'));

            return;
        }

        $this->errorMessage = null;

        try {
            $bookingService->create([
                'branch_id' => $space->branch_id,
                'user_id' => auth()->id(),
                'space_id' => $space->id,
                'title' => $this->title ?: null,
                'start_time' => $start,
                'end_time' => $end,
            ]);
        } catch (BookingConflictException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->showModal = false;
        $this->reset(['title', 'start_time', 'end_time']);
        session()->flash('status', __('Booking submitted successfully.'));
    }

    public function saveEvent(EventCreationService $eventCreation): void
    {
        $this->authorize('create', Event::class);

        $data = $this->validate([
            'eventTitle' => ['required', 'string', 'max:255'],
            'eventStartDate' => ['nullable', 'date'],
            'eventStartTime' => ['nullable', 'date_format:H:i'],
            'eventEndDate' => ['nullable', 'date', ...($this->eventStartDate ? ['after_or_equal:eventStartDate'] : [])],
            'eventEndTime' => ['nullable', 'date_format:H:i'],
            'eventLocation' => ['nullable', 'string', 'max:255'],
        ]);

        $registrationForm = $eventCreation->createWithRegistrationForm([
            'title' => $data['eventTitle'],
            'start_date' => $data['eventStartDate'] ?: null,
            'start_time' => $data['eventStartTime'] ?: null,
            'end_date' => $data['eventEndDate'] ?: null,
            'end_time' => $data['eventEndTime'] ?: null,
            'location' => $data['eventLocation'] ?: null,
        ]);

        $this->redirect(route('event-forms.builder', $registrationForm), navigate: true);
    }

    public function render()
    {
        $monthStart = CarbonImmutable::parse($this->month.'-01');
        $monthEnd = $monthStart->endOfMonth();

        $gridStart = $monthStart->startOfWeek(CarbonImmutable::SUNDAY);
        $gridEnd = $monthEnd->endOfWeek(CarbonImmutable::SUNDAY);

        $type = CalendarFilterType::from($this->type);

        $bookings = collect();

        if ($type !== CalendarFilterType::Event && $this->space_id) {
            $bookings = Booking::query()
                ->where('space_id', $this->space_id)
                ->whereIn('status', [BookingStatus::Approved, BookingStatus::Pending])
                ->where('start_time', '<=', $gridEnd)
                ->where('end_time', '>=', $gridStart)
                ->with('user')
                ->orderBy('start_time')
                ->get()
                ->groupBy(fn (Booking $booking) => $booking->start_time->format('Y-m-d'));
        }

        $events = collect();

        if ($type !== CalendarFilterType::Booking && $this->canViewEvents) {
            $events = $this->eventsByDay(Event::query()
                ->whereNotNull('start_date')
                ->where('start_date', '<=', $gridEnd)
                ->where(function ($query) use ($gridStart) {
                    $query->where('end_date', '>=', $gridStart)
                        ->orWhere(function ($query) use ($gridStart) {
                            $query->whereNull('end_date')->where('start_date', '>=', $gridStart);
                        });
                })
                ->with('registrationForm')
                ->orderBy('start_date')
                ->get(), $gridStart, $gridEnd);
        }

        $days = [];
        $cursor = $gridStart;

        while ($cursor->lte($gridEnd)) {
            $days[] = $cursor;
            $cursor = $cursor->addDay();
        }

        return view('livewire.bookings.calendar', [
            'days' => $days,
            'monthStart' => $monthStart,
            'bookingsByDay' => $bookings,
            'eventsByDay' => $events,
            'viewingBooking' => $this->viewBookingId ? Booking::with(['user', 'space'])->find($this->viewBookingId) : null,
            'viewingEvent' => $this->viewEventId ? Event::with('registrationForm')->find($this->viewEventId) : null,
            'spaces' => OfficeSpace::query()
                ->visibleTo(auth()->user())
                ->where('status', OfficeSpaceStatus::Active)
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Groups events by every day they span within [gridStart, gridEnd] —
     * unlike bookings, an event can run across multiple days, so it needs to
     * appear under each date's key, not just its start date.
     */
    private function eventsByDay(Collection $events, CarbonImmutable $gridStart, CarbonImmutable $gridEnd): Collection
    {
        $byDay = collect();

        foreach ($events as $event) {
            $start = CarbonImmutable::parse($event->start_date)->max($gridStart);
            $end = CarbonImmutable::parse($event->end_date ?? $event->start_date)->min($gridEnd);

            for ($cursor = $start; $cursor->lte($end); $cursor = $cursor->addDay()) {
                $key = $cursor->format('Y-m-d');
                $byDay->put($key, $byDay->get($key, collect())->push($event));
            }
        }

        return $byDay;
    }
}
