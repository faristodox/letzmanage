<?php

namespace App\Livewire;

use App\Enums\BookingStatus;
use App\Enums\RoleName;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\OfficeSpace;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DashboardStats extends Component
{
    public int $branchCount = 0;

    public int $officeSpaceCount = 0;

    public int $userCount = 0;

    public int $pendingApprovalCount = 0;

    public int $upcomingBookingCount = 0;

    public function mount(): void
    {
        $user = Auth::user();

        if ($user->hasRole(RoleName::Admin->value)) {
            $this->branchCount = Branch::count();
            $this->officeSpaceCount = OfficeSpace::count();
            $this->userCount = User::count();
            $this->pendingApprovalCount = Booking::where('status', BookingStatus::Pending)->count();
        } elseif ($user->hasRole(RoleName::Manager->value)) {
            $this->officeSpaceCount = OfficeSpace::where('branch_id', $user->branch_id)->count();
            $this->pendingApprovalCount = Booking::where('branch_id', $user->branch_id)
                ->where('status', BookingStatus::Pending)
                ->count();
        }

        $this->upcomingBookingCount = Booking::where('user_id', $user->id)
            ->where('status', BookingStatus::Approved)
            ->where('start_time', '>=', now())
            ->count();
    }

    public function render()
    {
        return view('livewire.dashboard-stats');
    }
}
