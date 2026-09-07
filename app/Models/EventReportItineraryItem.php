<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'event_id', 'time', 'activity', 'order'])]
class EventReportItineraryItem extends Model
{
    use BelongsToOrganization, HasFactory;

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
