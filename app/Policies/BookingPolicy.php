<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ViewOwnBookings->value)
            || $user->can(PermissionName::ViewAllBookings->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Booking $booking): bool
    {
        if ($user->hasRole(RoleName::Admin->value)) {
            return true;
        }

        if ($user->can(PermissionName::ViewAllBookings->value) && $user->branch_id === $booking->branch_id) {
            return true;
        }

        return $booking->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionName::CreateBookings->value);
    }

    /**
     * Determine whether the user can cancel the booking.
     */
    public function cancel(User $user, Booking $booking): bool
    {
        if ($user->hasRole(RoleName::Admin->value)) {
            return true;
        }

        if ($user->can(PermissionName::CancelAnyBooking->value) && $user->branch_id === $booking->branch_id) {
            return true;
        }

        return $booking->user_id === $user->id;
    }

    /**
     * Determine whether the user can approve the booking.
     */
    public function approve(User $user, Booking $booking): bool
    {
        if ($user->hasRole(RoleName::Admin->value)) {
            return true;
        }

        return $user->can(PermissionName::ApproveBookings->value) && $user->branch_id === $booking->branch_id;
    }

    /**
     * Determine whether the user can reject the booking.
     */
    public function reject(User $user, Booking $booking): bool
    {
        return $this->approve($user, $booking);
    }
}
