<?php

namespace App\Models;

use App\Enums\EventFormStatus;
use App\Enums\EventFormType;
use App\Enums\EventTransactionType;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['organization_id', 'title', 'slug', 'description', 'banner_path', 'status', 'created_by'])]
class Event extends Model
{
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'status' => EventFormStatus::class,
        ];
    }

    public function forms(): HasMany
    {
        return $this->hasMany(EventForm::class);
    }

    public function registrationForm(): HasOne
    {
        return $this->hasOne(EventForm::class)->where('type', EventFormType::Registration);
    }

    public function feedbackForm(): HasOne
    {
        return $this->hasOne(EventForm::class)->where('type', EventFormType::Feedback);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(EventTransaction::class)->orderByDesc('transaction_date');
    }

    public function reportDetail(): HasOne
    {
        return $this->hasOne(EventReportDetail::class);
    }

    public function itineraryItems(): HasMany
    {
        return $this->hasMany(EventReportItineraryItem::class)->orderBy('order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function totalIncome(): float
    {
        return (float) $this->transactions()->where('type', EventTransactionType::Income)->sum('amount');
    }

    public function totalExpenses(): float
    {
        return (float) $this->transactions()->where('type', EventTransactionType::Expense)->sum('amount');
    }

    /**
     * Payment gateway fees deducted from income transactions (e.g. CHIP's
     * cut) — tracked separately from totalIncome() so "Total Income" stays
     * the gross amount registrants actually paid, while netBalance() below
     * reflects real cash-in-hand.
     */
    public function totalGatewayFees(): float
    {
        return (float) $this->transactions()->whereNotNull('gateway_fee_amount')->sum('gateway_fee_amount');
    }

    public function netBalance(): float
    {
        return $this->totalIncome() - $this->totalExpenses() - $this->totalGatewayFees();
    }

    public function bannerUrl(): ?string
    {
        return $this->banner_path ? Storage::url($this->banner_path) : null;
    }

    public function publicUrl(): string
    {
        return route('event-registration.show', ['organization' => $this->organization, 'eventSlug' => $this->slug]);
    }

    public function checkinUrl(): string
    {
        return route('event-checkin.show', ['organization' => $this->organization, 'eventSlug' => $this->slug]);
    }

    public function feedbackUrl(): string
    {
        return route('event-feedback.show', ['organization' => $this->organization, 'eventSlug' => $this->slug]);
    }

    /**
     * Generate a slug unique within the current organization, mirroring
     * OrganizationProvisioner::uniqueSlug() for globally-unique org slugs.
     */
    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'event';
        $slug = $base;
        $suffix = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
