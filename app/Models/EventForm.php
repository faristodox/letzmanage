<?php

namespace App\Models;

use App\Enums\CheckInVerificationMode;
use App\Enums\EventFormStatus;
use App\Enums\EventFormType;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable([
    'organization_id', 'event_id', 'type', 'status', 'closes_at', 'created_by',
    'checkin_enabled', 'checkin_starts_at', 'checkin_ends_at', 'checkin_link_enabled', 'checkin_qr_enabled',
    'checkin_manual_enabled', 'checkin_verification_field_ids', 'checkin_verification_mode', 'checkin_onsite_registration_enabled',
    'payment_enabled', 'payment_methods', 'payment_amount', 'pricing_field_id',
])]
class EventForm extends Model
{
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => EventFormType::class,
            'status' => EventFormStatus::class,
            'closes_at' => 'datetime',
            'checkin_enabled' => 'boolean',
            'checkin_starts_at' => 'datetime',
            'checkin_ends_at' => 'datetime',
            'checkin_link_enabled' => 'boolean',
            'checkin_qr_enabled' => 'boolean',
            'checkin_manual_enabled' => 'boolean',
            'checkin_verification_field_ids' => 'array',
            'checkin_verification_mode' => CheckInVerificationMode::class,
            'checkin_onsite_registration_enabled' => 'boolean',
            'payment_enabled' => 'boolean',
            'payment_methods' => 'array',
            'payment_amount' => 'decimal:2',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(EventFormField::class)->orderBy('order');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(EventFormResponse::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(EventCheckIn::class);
    }

    public function pricingField(): BelongsTo
    {
        return $this->belongsTo(EventFormField::class, 'pricing_field_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isAcceptingResponses(): bool
    {
        if ($this->status !== EventFormStatus::Published) {
            return false;
        }

        return $this->closes_at === null || $this->closes_at->isFuture();
    }

    /**
     * The fields the admin selected for check-in verification, resolved
     * against this form's own fields (never a foreign/stale set).
     */
    public function checkinFields(): Collection
    {
        $ids = $this->checkin_verification_field_ids ?? [];

        return $this->fields->whereIn('id', $ids);
    }

    public function isCheckinOpen(): bool
    {
        if (! $this->checkin_enabled) {
            return false;
        }

        if ($this->checkin_starts_at && $this->checkin_starts_at->isFuture()) {
            return false;
        }

        return ! ($this->checkin_ends_at && $this->checkin_ends_at->isPast());
    }

    /**
     * What a response with these answers owes: the flat payment_amount, or —
     * when priced by field — the price of the option selected for
     * pricing_field_id. Null means "not payable" (payment disabled, or the
     * pricing field wasn't answered/has no price configured for that option).
     */
    public function resolveAmountFor(array $answers): ?float
    {
        if (! $this->payment_enabled) {
            return null;
        }

        if ($this->pricing_field_id === null) {
            return $this->payment_amount !== null ? (float) $this->payment_amount : null;
        }

        $selected = $answers[$this->pricing_field_id] ?? null;

        if ($selected === null || $this->pricingField === null) {
            return null;
        }

        $prices = $this->pricingField->option_prices ?? [];

        return isset($prices[$selected]) ? (float) $prices[$selected] : null;
    }
}
