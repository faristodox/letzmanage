<?php

namespace App\Models;

use App\Enums\EventPaymentMethod;
use App\Enums\EventPaymentStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'organization_id', 'event_form_response_id', 'method', 'amount', 'currency', 'status',
    'chip_purchase_id', 'receipt_path', 'paid_at', 'reviewed_by', 'reviewed_at', 'event_transaction_id',
])]
class EventPayment extends Model
{
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'method' => EventPaymentMethod::class,
            'status' => EventPaymentStatus::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(EventFormResponse::class, 'event_form_response_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(EventTransaction::class, 'event_transaction_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function receiptUrl(): ?string
    {
        return $this->receipt_path ? Storage::url($this->receipt_path) : null;
    }
}
