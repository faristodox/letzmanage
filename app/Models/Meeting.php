<?php

namespace App\Models;

use App\Enums\MeetingStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'event_id', 'created_by', 'archived_file_id', 'title', 'status', 'audio_gcs_object', 'gcs_operation_name', 'transcript', 'minutes', 'minutes_ms', 'agenda_items', 'agenda_items_ms', 'duration_seconds', 'failure_reason'])]
class Meeting extends Model
{
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'status' => MeetingStatus::class,
            'agenda_items' => 'array',
            'agenda_items_ms' => 'array',
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
     * Still moving through the pipeline (Pending/Uploading/Transcribing/
     * Summarizing) as opposed to a terminal state (Ready or Failed).
     */
    public function isProcessing(): bool
    {
        return ! in_array($this->status, [MeetingStatus::Ready, MeetingStatus::Failed], true);
    }
}
