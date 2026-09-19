<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An event scraped from the Jawatankuasa WANITA committee's own Google
 * Sheet calendar — see App\Services\WanitaCalendarSyncService. Unlike
 * Holiday, this is org-scoped: it's one organization's own committee
 * calendar, not reference data shared across tenants.
 */
#[Fillable(['organization_id', 'date', 'title', 'event_id'])]
class WanitaCalendarEvent extends Model
{
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * Set once this sheet entry has been promoted to a real Event (see
     * WanitaCalendarSyncService::sync()) — null until then, and never
     * touched again by a later sync once set, even if the sheet changes.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
