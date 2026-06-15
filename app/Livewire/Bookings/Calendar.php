<?php

namespace App\Livewire\Bookings;

use App\Enums\BookingStatus;
use App\Enums\OfficeSpaceStatus;
use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use App\Models\OfficeSpace;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Livewire\Component;

class Calendar extends Component
{
    public ?int $space_id = null;

    public string $month;

    public bool $showModal = false;

    public string $date = '';

    public string $title = '';

    public string $start_time = '';

    public string $end_time = '';

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->authorize('create', Booking::class);

        $this->month = now()->format('Y-m');

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
        $this->errorMessage = null;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['title', 'start_time', 'end_time']);
        $this->resetValidation();
        $this->errorMessage = null;
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

        $start = \Carbon\Carbon::parse("{$this->date} {$this->start_time}");
        $end = \Carbon\Carbon::parse("{$this->date} {$this->end_time}");

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

    public function render()
    {
        $monthStart = CarbonImmutable::parse($this->month.'-01');
        $monthEnd = $monthStart->endOfMonth();

        $gridStart = $monthStart->startOfWeek(CarbonImmutable::SUNDAY);
        $gridEnd = $monthEnd->endOfWeek(CarbonImmutable::SUNDAY);

        $bookings = collect();

        if ($this->space_id) {
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
            'spaces' => OfficeSpace::query()
                ->visibleTo(auth()->user())
                ->where('status', OfficeSpaceStatus::Active)
                ->orderBy('name')
                ->get(),
        ]);
    }
}
