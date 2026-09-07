<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['organization_id', 'event_form_id', 'reference', 'answers', 'submitted_ip'])]
class EventFormResponse extends Model
{
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'answers' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $response): void {
            $response->update(['reference' => sprintf('REG-%06d', $response->id)]);
        });
    }

    public function eventForm(): BelongsTo
    {
        return $this->belongsTo(EventForm::class);
    }

    public function checkIn(): HasOne
    {
        return $this->hasOne(EventCheckIn::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(EventPayment::class);
    }
}
