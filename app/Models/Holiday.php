<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A Malaysia public holiday — global reference data, not scoped to any one
 * organization. See App\Console\Commands\SyncMalaysiaHolidays.
 */
#[Fillable(['date', 'title', 'description'])]
class Holiday extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
