<?php

namespace App\Models;

use App\Enums\HolidaySource;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'source', 'state'])]
class HolidayCalendarSetting extends Model
{
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'source' => HolidaySource::class,
        ];
    }
}
