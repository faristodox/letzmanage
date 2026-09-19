<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An event scraped from the Jawatankuasa WANITA committee's own Google
 * Sheet calendar — see App\Services\WanitaCalendarSyncService. Unlike
 * Holiday, this is org-scoped: it's one organization's own committee
 * calendar, not reference data shared across tenants.
 */
#[Fillable(['organization_id', 'date', 'title'])]
class WanitaCalendarEvent extends Model
{
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
