<?php

namespace App\Models;

use App\Enums\HolidaySource;
use App\Enums\HolidayType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A Malaysia public or school holiday — global reference data, not scoped to
 * any one organization (the same holidays apply to every tenant; only which
 * source/state to display is a per-organization choice, see
 * App\Models\HolidayCalendarSetting). Populated by either
 * App\Console\Commands\SyncMalaysiaHolidays (source=google) or
 * App\Services\CutiSekolahHolidayService (source=cutisekolah).
 */
#[Fillable(['date', 'end_date', 'title', 'description', 'source', 'type', 'state', 'applicable_states'])]
class Holiday extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'end_date' => 'date',
            'source' => HolidaySource::class,
            'type' => HolidayType::class,
            'applicable_states' => 'array',
        ];
    }

    /**
     * Whether this holiday applies to the given state — always true when
     * applicable_states is null (nationwide). Only meaningful for
     * type=public rows; school holidays are already single-state via the
     * plain `state` column.
     */
    public function appliesToState(?string $state): bool
    {
        if ($this->applicable_states === null) {
            return true;
        }

        return $state !== null && in_array($state, $this->applicable_states, true);
    }
}
