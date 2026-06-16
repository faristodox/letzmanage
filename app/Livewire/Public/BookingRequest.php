<?php

namespace App\Livewire\Public;

use App\Enums\BookingStatus;
use App\Enums\BranchStatus;
use App\Enums\OfficeSpaceStatus;
use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\OfficeSpace;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

class BookingRequest extends Component
{
    private const MAX_DURATION_MINUTES = 1440;

    public int $step = 1;

    public ?int $branch_id = null;

    public ?int $space_id = null;

    public string $title = '';

    public string $date = '';

    public int $weekOffset = 0;

    public string $start_time = '';

    public string $end_time = '';

    public string $guest_name = '';

    public string $guest_email = '';

    public string $guest_phone = '';

    public ?string $errorMessage = null;

    public bool $submitted = false;

    public bool $autoApproved = false;

    public int $slotInterval = 30;

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');

        $activeBranches = Branch::query()->where('status', BranchStatus::Active)->orderBy('name')->get();

        if ($activeBranches->count() === 1) {
            $this->branch_id = $activeBranches->first()->id;
        }
    }

    public function selectBranch(int $branchId): void
    {
        $this->branch_id = $branchId;
        $this->space_id = null;
    }

    public function changeBranch(): void
    {
        $this->branch_id = null;
        $this->space_id = null;
        $this->step = 1;
    }

    public function selectSpace(int $spaceId): void
    {
        $this->space_id = $spaceId;
        $this->date = now()->format('Y-m-d');
        $this->weekOffset = 0;
        $this->start_time = '';
        $this->end_time = '';
        $this->errorMessage = null;
        $this->step = 2;
    }

    public function backToSpaces(): void
    {
        $this->step = 1;
        $this->errorMessage = null;
    }

    public function backToSchedule(): void
    {
        $this->step = 2;
        $this->errorMessage = null;
    }

    public function previousWeek(): void
    {
        $this->weekOffset = max(0, $this->weekOffset - 1);
    }

    public function nextWeek(): void
    {
        $this->weekOffset++;
    }

    public function selectDay(string $date): void
    {
        $this->date = $date;
        $this->start_time = '';
        $this->end_time = '';
    }

    public function updatedSlotInterval(): void
    {
        $this->start_time = '';
        $this->end_time = '';
    }

    public function selectSlot(string $time): void
    {
        $this->start_time = $time;
        $this->end_time = '';
    }

    public function selectEndSlot(string $time): void
    {
        $this->end_time = $time;
    }

    public function proceedToDetails(BookingService $bookingService): void
    {
        $this->errorMessage = null;

        $this->validate([
            'space_id' => ['required', 'integer', 'exists:office_spaces,id'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
        ]);

        $start = Carbon::parse("{$this->date} {$this->start_time}");
        $end = Carbon::parse("{$this->date} {$this->end_time}");

        if ($end->lte($start)) {
            $end->addDay();
        }

        if ($start->isPast()) {
            $this->errorMessage = __('The selected time must be in the future.');

            return;
        }

        if ($bookingService->hasOverlap($this->space_id, $start, $end)) {
            $this->errorMessage = __('That time slot was just booked. Please choose another.');

            return;
        }

        $this->step = 3;
    }

    public function submit(BookingService $bookingService): void
    {
        $this->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'space_id' => ['required', 'integer', 'exists:office_spaces,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_email' => ['required', 'email', 'max:255'],
            'guest_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $space = OfficeSpace::findOrFail($this->space_id);
        $start = Carbon::parse("{$this->date} {$this->start_time}");
        $end = Carbon::parse("{$this->date} {$this->end_time}");

        if ($end->lte($start)) {
            $end->addDay();
        }

        if ($start->isPast()) {
            $this->errorMessage = __('The start time must be in the future.');
            $this->step = 2;

            return;
        }

        $this->errorMessage = null;

        try {
            $booking = $bookingService->createGuestBooking([
                'branch_id' => $space->branch_id,
                'space_id' => $space->id,
                'title' => $this->title ?: null,
                'start_time' => $start,
                'end_time' => $end,
                'guest_name' => $this->guest_name,
                'guest_email' => $this->guest_email,
                'guest_phone' => $this->guest_phone ?: null,
            ]);
        } catch (BookingConflictException $e) {
            $this->errorMessage = $e->getMessage();
            $this->step = 2;

            return;
        }

        $this->autoApproved = $booking->status === BookingStatus::Approved;
        $this->submitted = true;
    }

    public function render()
    {
        $spaces = collect();

        if ($this->branch_id) {
            $spaces = OfficeSpace::query()
                ->where('branch_id', $this->branch_id)
                ->where('status', OfficeSpaceStatus::Active)
                ->with('type')
                ->orderBy('name')
                ->get();
        }

        $selectedSpace = $this->space_id
            ? OfficeSpace::query()->with('type')->find($this->space_id)
            : null;

        $days = collect();

        for ($i = 0; $i < 7; $i++) {
            $days->push(now()->addDays($this->weekOffset * 7 + $i)->startOfDay());
        }

        $startSlots = collect();
        $endSlots = collect();

        if ($this->space_id && $this->date) {
            $approvedBookings = $this->approvedBookingsForDate();
            $startSlots = $this->buildStartSlots($approvedBookings);

            if ($this->start_time) {
                $endSlots = $this->buildEndSlots($approvedBookings);
            }
        }

        return view('livewire.public.booking-request', [
            'branches' => Branch::query()->where('status', BranchStatus::Active)->orderBy('name')->get(),
            'spaces' => $spaces,
            'selectedSpace' => $selectedSpace,
            'days' => $days,
            'startSlots' => $startSlots,
            'endSlots' => $endSlots,
            'slotIntervalOptions' => [30 => '30 min', 60 => '1 hour', 90 => '1.5 hours', 120 => '2 hours'],
        ]);
    }

    /**
     * Approved bookings overlapping the selected date (00:00-23:59) or the
     * early hours of the following day (up to the max bookable duration),
     * including any booking that started the previous day and runs past
     * midnight. The extended window lets end-time options that cross
     * midnight correctly account for bookings on the next calendar day.
     */
    private function approvedBookingsForDate(): Collection
    {
        $dayStart = Carbon::parse($this->date)->startOfDay();
        $dayEnd = $dayStart->copy()->addDay()->addMinutes(self::MAX_DURATION_MINUTES);

        $spaceIds = app(BookingService::class)->conflictingSpaceIds($this->space_id);

        return Booking::query()
            ->whereIn('space_id', $spaceIds)
            ->where('status', BookingStatus::Approved)
            ->where('start_time', '<', $dayEnd)
            ->where('end_time', '>', $dayStart)
            ->get(['start_time', 'end_time']);
    }

    /**
     * Build the list of selectable start times for the selected date (00:00-23:30),
     * marking each as available or not based on existing approved bookings.
     */
    private function buildStartSlots(Collection $approvedBookings): Collection
    {
        $dayStart = Carbon::parse($this->date)->startOfDay();
        $dayEnd = $dayStart->copy()->addDay();
        $cursor = $dayStart->copy();

        $slots = collect();

        while ($cursor->lt($dayEnd)) {
            if (! $cursor->isPast()) {
                $available = ! $approvedBookings->contains(
                    fn (Booking $booking) => $cursor->gte($booking->start_time) && $cursor->lt($booking->end_time)
                );

                $slots->push([
                    'time' => $cursor->format('H:i'),
                    'label' => $cursor->format('g:i A'),
                    'available' => $available,
                ]);
            }

            $cursor = $cursor->copy()->addMinutes($this->slotInterval);
        }

        return $slots;
    }

    /**
     * Build the list of selectable end times for the currently selected
     * start time, stopping at the next approved booking or the maximum
     * bookable duration - whichever comes first. End times may fall on the
     * following calendar day (e.g. an 11pm start can end at 1am).
     */
    private function buildEndSlots(Collection $approvedBookings): Collection
    {
        $start = Carbon::parse("{$this->date} {$this->start_time}");

        $limit = $start->copy()->addMinutes(self::MAX_DURATION_MINUTES);

        $nextBookingStart = $approvedBookings
            ->filter(fn (Booking $booking) => $booking->start_time->gt($start))
            ->min('start_time');

        if ($nextBookingStart) {
            $limit = $limit->min($nextBookingStart);
        }

        $slots = collect();

        if ($limit->lte($start)) {
            return $slots;
        }

        $cursor = $start->copy()->addMinutes($this->slotInterval);

        if ($cursor->gt($limit)) {
            // The next approved booking (or close time) falls before the
            // first grid-aligned option - offer it as a single end time.
            $slots->push($this->endSlotData($start, $limit));

            return $slots;
        }

        while ($cursor->lte($limit)) {
            $slots->push($this->endSlotData($start, $cursor));

            $cursor = $cursor->copy()->addMinutes($this->slotInterval);
        }

        return $slots;
    }

    private function endSlotData(Carbon $start, Carbon $end): array
    {
        return [
            'time' => $end->format('H:i'),
            'label' => $end->format('g:i A'),
            'duration' => $this->formatDuration($start->diffInMinutes($end)),
            'nextDay' => ! $end->isSameDay($start),
        ];
    }

    private function formatDuration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        if ($hours === 0) {
            return "{$minutes}m";
        }

        if ($remainder === 0) {
            return "{$hours}h";
        }

        return "{$hours}h {$remainder}m";
    }
}
