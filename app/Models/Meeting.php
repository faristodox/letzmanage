<?php

namespace App\Models;

use App\Enums\MeetingAttendanceMode;
use App\Enums\MeetingStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'event_id', 'created_by', 'archived_file_id', 'title', 'status', 'audio_gcs_object', 'gcs_operation_name', 'transcript', 'minutes', 'minutes_ms', 'duration_seconds', 'failure_reason', 'attendance_mode', 'checkin_token', 'allow_new_registration'])]
class Meeting extends Model
{
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'status' => MeetingStatus::class,
            'attendance_mode' => MeetingAttendanceMode::class,
            'allow_new_registration' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function archivedFile(): BelongsTo
    {
        return $this->belongsTo(ArchivedFile::class);
    }

    /**
     * Confirmed attendees via the public check-in flow (see
     * Livewire\Public\MeetingCheckIn) — either roster members or walk-in
     * guests recorded through "Allow new registration". Ground truth
     * GenerateMeetingMinutesJob prefers over guessing attendees from the
     * transcript when non-empty.
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(MeetingAttendance::class);
    }

    public function checkInUrl(): ?string
    {
        return $this->checkin_token ? route('meetings.checkin.show', ['token' => $this->checkin_token]) : null;
    }

    /**
     * Still moving through the pipeline (Pending/Uploading/Transcribing/
     * Summarizing) as opposed to a terminal state (Ready or Failed).
     */
    public function isProcessing(): bool
    {
        return ! in_array($this->status, [MeetingStatus::Ready, MeetingStatus::Failed], true);
    }
}
