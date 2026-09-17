<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The organization's committee/board roster — an authoritative reference
 * Meeting Minutes generation cross-checks transcript attendees against, so
 * the Attendees section uses the exact registered name and position instead
 * of whatever a possibly-garbled ASR transcript produced. See
 * GeminiSummaryService::summarize().
 */
#[Fillable(['organization_id', 'name', 'position'])]
class CommitteeMember extends Model
{
    use BelongsToOrganization, HasFactory;
}
