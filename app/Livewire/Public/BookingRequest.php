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
        // Only active spaces are bookable; ignore maintenance/invalid ids.
        $space = OfficeSpace::query()
            ->where('status', OfficeSpaceStatus::Active)
            ->find($spaceId);

        if (! $space) {
            return;
        }

        $this->space_id = $space->id;
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
                ->whereIn('status', [OfficeSpaceStatus::Active, OfficeSpaceStatus::Maintenance])
                ->with(['type', 'parent'])
                ->orderBy('status') // 'active' sorts before 'maintenance'
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
            $pendingBookings = $this->pendingBookingsForDate();
            $startSlots = $this->buildStartSlots($approvedBookings, $pendingBookings);

            if ($this->start_time) {
                $endSlots = $this->buildEndSlots($approvedBookings, $pendingBookings);
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

    private function approvedBookingsForDate(): Collection
    {
        return $this->bookingsForDate(BookingStatus::Approved);
    }

    private function pendingBookingsForDate(): Collection
    {
        return $this->bookingsForDate(BookingStatus::Pending);
    }

    private function bookingsForDate(BookingStatus $status): Collection
    {
        $dayStart = Carbon::parse($this->date)->startOfDay();
        $dayEnd = $dayStart->copy()->addDay()->addMinutes(self::MAX_DURATION_MINUTES);

        $spaceIds = app(BookingService::class)->conflictingSpaceIds($this->space_id);

        return Booking::query()
            ->whereIn('space_id', $spaceIds)
            ->where('status', $status)
            ->where('start_time', '<', $dayEnd)
            ->where('end_time', '>', $dayStart)
            ->get(['start_time', 'end_time']);
    }

    private function buildStartSlots(Collection $approvedBookings, Collection $pendingBookings): Collection
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

                $tbc = $available && $pendingBookings->contains(
                    fn (Booking $booking) => $cursor->gte($booking->start_time) && $cursor->lt($booking->end_time)
                );

                $slots->push([
                    'time' => $cursor->format('H:i'),
                    'label' => $cursor->format('g:i A'),
                    'available' => $available,
                    'tbc' => $tbc,
                ]);
            }

            $cursor = $cursor->copy()->addMinutes($this->slotInterval);
        }

        return $slots;
    }

    private function buildEndSlots(Collection $approvedBookings, Collection $pendingBookings): Collection
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
            $slots->push($this->endSlotData($start, $limit, $pendingBookings));

            return $slots;
        }

        while ($cursor->lte($limit)) {
            $slots->push($this->endSlotData($start, $cursor, $pendingBookings));

            $cursor = $cursor->copy()->addMinutes($this->slotInterval);
        }

        return $slots;
    }

    private function endSlotData(Carbon $start, Carbon $end, Collection $pendingBookings): array
    {
        $tbc = $pendingBookings->contains(
            fn (Booking $booking) => $booking->start_time->lt($end) && $booking->end_time->gt($start)
        );

        return [
            'time' => $end->format('H:i'),
            'label' => $end->format('g:i A'),
            'duration' => $this->formatDuration($start->diffInMinutes($end)),
            'nextDay' => ! $end->isSameDay($start),
            'tbc' => $tbc,
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
