<?php

namespace App\Models;

use App\Enums\EventCheckInMethod;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'event_form_id', 'event_form_response_id', 'method', 'checked_in_by', 'checked_in_at'])]
class EventCheckIn extends Model
{
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'method' => EventCheckInMethod::class,
            'checked_in_at' => 'datetime',
        ];
    }

    public function eventForm(): BelongsTo
    {
        return $this->belongsTo(EventForm::class);
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(EventFormResponse::class, 'event_form_response_id');
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }
}
