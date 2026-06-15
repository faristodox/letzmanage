<?php

namespace App\Livewire\Bookings;

use App\Enums\BookingStatus;
use App\Enums\OfficeSpaceStatus;
use App\Enums\RoleName;
use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use App\Models\OfficeSpace;
use App\Services\BookingService;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $statusFilter = '';

    public bool $showModal = false;

    public ?int $space_id = null;

    public string $title = '';

    public string $date = '';

    public string $start_time = '';

    public string $end_time = '';

    public ?int $approvingId = null;

    public string $approveNote = '';

    public ?int $rejectingId = null;

    public string $rejectReason = '';

    public ?int $confirmingCancelId = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Booking::class);
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', Booking::class);

        $this->reset(['space_id', 'title', 'date', 'start_time', 'end_time']);
        $this->date = now()->addDay()->format('Y-m-d');
        $this->start_time = '09:00';
        $this->end_time = '10:00';
        $this->errorMessage = null;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['space_id', 'title', 'date', 'start_time', 'end_time']);
        $this->resetValidation();
        $this->errorMessage = null;
    }

    public function save(BookingService $bookingService): void
    {
        $this->authorize('create', Booking::class);

        $this->validate([
            'space_id' => ['required', 'integer', 'exists:office_spaces,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
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
        $this->reset(['space_id', 'title', 'date', 'start_time', 'end_time']);
        session()->flash('status', __('Booking submitted successfully.'));
    }

    public function confirmApprove(int $id): void
    {
        $booking = Booking::findOrFail($id);
        $this->authorize('approve', $booking);

        $this->approvingId = $id;
        $this->approveNote = '';
    }

    public function approve(BookingService $bookingService): void
    {
        $booking = Booking::findOrFail($this->approvingId);
        $this->authorize('approve', $booking);

        try {
            $bookingService->approve($booking, auth()->user(), $this->approveNote ?: null);
            $this->approvingId = null;
            $this->approveNote = '';
            session()->flash('status', __('Booking approved.'));
        } catch (BookingConflictException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function closeApproveModal(): void
    {
        $this->approvingId = null;
        $this->approveNote = '';
    }

    public function confirmReject(int $id): void
    {
        $booking = Booking::findOrFail($id);
        $this->authorize('reject', $booking);

        $this->rejectingId = $id;
        $this->rejectReason = '';
    }

    public function reject(BookingService $bookingService): void
    {
        $booking = Booking::findOrFail($this->rejectingId);
        $this->authorize('reject', $booking);

        $bookingService->reject($booking, auth()->user(), $this->rejectReason ?: null);

        $this->rejectingId = null;
        $this->rejectReason = '';
        session()->flash('status', __('Booking rejected.'));
    }

    public function closeRejectModal(): void
    {
        $this->rejectingId = null;
        $this->rejectReason = '';
    }

    public function confirmCancel(int $id): void
    {
        $booking = Booking::findOrFail($id);
        $this->authorize('cancel', $booking);

        $this->confirmingCancelId = $id;
    }

    public function closeCancelModal(): void
    {
        $this->confirmingCancelId = null;
    }

    public function cancel(BookingService $bookingService): void
    {
        $booking = Booking::findOrFail($this->confirmingCancelId);
        $this->authorize('cancel', $booking);

        $bookingService->cancel($booking);

        $this->confirmingCancelId = null;
        session()->flash('status', __('Booking cancelled.'));
    }

    public function render()
    {
        $user = auth()->user();

        $bookings = Booking::query()
            ->visibleTo($user)
            ->with(['space', 'user', 'branch'])
            ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
            ->orderByDesc('start_time')
            ->paginate(10);

        $spaces = OfficeSpace::query()
            ->visibleTo($user)
            ->with('type')
            ->where('status', OfficeSpaceStatus::Active)
            ->orderBy('name')
            ->get();

        return view('livewire.bookings.index', [
            'bookings' => $bookings,
            'spaces' => $spaces,
            'statuses' => BookingStatus::cases(),
            'showAllColumns' => $user->hasRole(RoleName::Admin->value) || $user->can('view all bookings'),
        ]);
    }
}
