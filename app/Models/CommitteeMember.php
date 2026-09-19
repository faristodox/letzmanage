<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * The organization's committee/board roster — an authoritative reference
 * Meeting Minutes generation cross-checks transcript attendees against, so
 * the Attendees section uses the exact registered name and position instead
 * of whatever a possibly-garbled ASR transcript produced. See
 * GeminiSummaryService::summarize().
 */
#[Fillable(['organization_id', 'portfolio_id', 'user_id', 'name', 'position', 'ic_number'])]
class CommitteeMember extends Model
{
    use BelongsToOrganization, HasFactory;

    /**
     * Full history of Committee Meeting events this member has checked into
     * — surfaced from the Committee Members list for annual-report reference.
     */
    public function attendedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_attendances')->withPivot('checked_in_at');
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    /**
     * Set only for the subset of roster entries who also have a Letz Manage
     * login — most committee members are attendees only and leave this null.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Masks all but the last 4 characters — the admin list shows this by
     * default rather than the full IC/MyKad number, since there's no need to
     * display the whole thing for routine reference.
     */
    public function maskedIcNumber(): ?string
    {
        if (! $this->ic_number) {
            return null;
        }

        $length = strlen($this->ic_number);

        return $length <= 4
            ? $this->ic_number
            : str_repeat('•', $length - 4).substr($this->ic_number, -4);
    }
}
