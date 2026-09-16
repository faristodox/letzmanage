<?php

namespace App\Models;

use App\Contracts\GoogleCalendarSyncable;
use App\Enums\EventFormType;
use App\Enums\EventStatus;
use App\Enums\EventTransactionType;
use App\Models\Concerns\BelongsToOrganization;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['organization_id', 'title', 'slug', 'description', 'banner_path', 'status', 'created_by', 'start_date', 'start_time', 'end_date', 'end_time', 'location', 'google_event_id'])]
class Event extends Model implements GoogleCalendarSyncable
{
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
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

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
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

    /**
     * Human-readable event schedule, omitting whatever wasn't filled in —
     * e.g. no end date/time shown at all for a one-day event.
     */
    public function scheduleLabel(): ?string
    {
        if (! $this->start_date) {
            return null;
        }

        $label = $this->start_date->format('d M Y');

        if ($this->start_time) {
            $label .= ', '.$this->formatTime($this->start_time);
        }

        if ($this->end_date && ! $this->end_date->isSameDay($this->start_date)) {
            $label .= ' – '.$this->end_date->format('d M Y');

            if ($this->end_time) {
                $label .= ', '.$this->formatTime($this->end_time);
            }
        } elseif ($this->end_time) {
            $label .= ' – '.$this->formatTime($this->end_time);
        }

        return $label;
    }

    private function formatTime(string $time): string
    {
        return Carbon::createFromFormat('H:i', $time)->format('g:i A');
    }

    /**
     * Assumes start_date is set — callers (EventCalendarSyncService) only
     * sync an event once it has one. Renders as an all-day event when no
     * start_time was given, otherwise a timed event; a missing end_time
     * defaults to a 1-hour duration.
     */
    public function googleEventPayload(): array
    {
        $endDate = $this->end_date ?: $this->start_date;

        if ($this->start_time) {
            $start = $this->combineDateAndTime($this->start_date, $this->start_time);
            $end = $this->end_time
                ? $this->combineDateAndTime($endDate, $this->end_time)
                : $start->copy()->addHour();

            // An end time at or before the start, with no explicit end_date
            // override, means the event actually runs past midnight — roll
            // to the next calendar day rather than sending Google an
            // end-before-start range (rejected with "timeRangeEmpty").
            if ($end->lte($start)) {
                $end = $end->copy()->addDay();
            }

            $schedule = [
                'start' => ['dateTime' => $start->toRfc3339String(), 'timeZone' => config('app.timezone')],
                'end' => ['dateTime' => $end->toRfc3339String(), 'timeZone' => config('app.timezone')],
            ];
        } else {
            // All-day event(s) — Google's end.date is exclusive (one day past the last day).
            $schedule = [
                'start' => ['date' => $this->start_date->format('Y-m-d')],
                'end' => ['date' => $endDate->copy()->addDay()->format('Y-m-d')],
            ];
        }

        return [
            'summary' => $this->title,
            'description' => (string) $this->description,
            'location' => $this->location,
            ...$schedule,
        ];
    }

    private function combineDateAndTime(Carbon $date, string $time): Carbon
    {
        [$hour, $minute] = explode(':', $time);

        return $date->copy()->setTime((int) $hour, (int) $minute);
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
