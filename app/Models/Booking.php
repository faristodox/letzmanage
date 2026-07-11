<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'branch_id', 'user_id', 'guest_name', 'guest_email', 'guest_phone', 'space_id', 'title', 'start_time', 'end_time', 'status', 'approved_by', 'notes'])]
class Booking extends Model
{
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'start_time' => 'datetime',
            'end_time' => 'datetime',
        ];
    }

    /**
     * Whether this booking was made by a guest (no user account).
     */
    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Display name of whoever requested the booking, whether a user or a guest.
     */
    public function requesterName(): string
    {
        return $this->user?->name ?? $this->guest_name ?? 'Guest';
    }

    /**
     * Scope bookings to what the given user is allowed to see:
     * Admins see everything, branch approvers see their branch,
     * everyone else sees only their own bookings.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole(RoleName::Admin->value)) {
            return $query;
        }

        if ($user->can(PermissionName::ViewAllBookings->value)) {
            return $query->where('branch_id', $user->branch_id);
        }

        return $query->where('user_id', $user->id);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(OfficeSpace::class, 'space_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
