<?php

namespace App\Models;

use App\Enums\EventTransactionSource;
use App\Enums\EventTransactionType;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id', 'event_id', 'event_form_response_id', 'type', 'category',
    'amount', 'gateway_fee_amount', 'transaction_date', 'description', 'source', 'recorded_by',
])]
class EventTransaction extends Model
{
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => EventTransactionType::class,
            'source' => EventTransactionSource::class,
            'amount' => 'decimal:2',
            'gateway_fee_amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function netAmount(): ?float
    {
        return $this->gateway_fee_amount !== null
            ? (float) $this->amount - (float) $this->gateway_fee_amount
            : null;
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(EventFormResponse::class, 'event_form_response_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
