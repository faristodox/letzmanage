<?php

namespace App\Models;

use App\Enums\OfficeSpaceStatus;
use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['branch_id', 'parent_id', 'name', 'image_path', 'type_id', 'capacity', 'facilities', 'status'])]
class OfficeSpace extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => OfficeSpaceStatus::class,
            'facilities' => 'array',
            'capacity' => 'integer',
        ];
    }

    /**
     * Scope office spaces to what the given user is allowed to see:
     * Admins see every branch, everyone else sees only their own branch.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole(RoleName::Admin->value)) {
            return $query;
        }

        return $query->where('branch_id', $user->branch_id);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(OfficeSpace::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(OfficeSpace::class, 'parent_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(OfficeSpaceType::class, 'type_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'space_id');
    }
}
