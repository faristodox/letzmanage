<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'status', 'spi_enabled'])]
class Organization extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
            'spi_enabled' => 'boolean',
        ];
    }

    public function hasSpiEnabled(): bool
    {
        return $this->spi_enabled === true;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function officeSpaces(): HasMany
    {
        return $this->hasMany(OfficeSpace::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
