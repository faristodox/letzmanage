<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One confirmed attendee for a meeting, recorded via the public check-in
 * flow (Livewire\Public\MeetingCheckIn) — either a committee roster member
 * (committee_member_id set) or a walk-in guest recorded through "Allow new
 * registration" (committee_member_id null, guest_* filled in instead).
 */
#[Fillable(['meeting_id', 'committee_member_id', 'guest_name', 'guest_position', 'guest_ic_number', 'checked_in_at'])]
class MeetingAttendance extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function committeeMember(): BelongsTo
    {
        return $this->belongsTo(CommitteeMember::class);
    }

    public function displayName(): string
    {
        return $this->committeeMember?->name ?? (string) $this->guest_name;
    }

    public function displayPosition(): ?string
    {
        return $this->committeeMember?->position ?? $this->guest_position;
    }
}
