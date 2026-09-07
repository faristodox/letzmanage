<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'organization_id', 'event_id', 'event_date', 'event_time', 'theme', 'venue',
    'objectives', 'problems', 'achievements', 'directors_remarks',
    'prepared_by_name', 'prepared_by_position', 'prepared_by_date', 'prepared_by_signature_path',
    'reviewed_by_name', 'reviewed_by_position', 'reviewed_by_date', 'reviewed_by_signature_path',
    'approved_by_name', 'approved_by_position', 'approved_by_date', 'approved_by_signature_path',
])]
class EventReportDetail extends Model
{
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'prepared_by_date' => 'date',
            'reviewed_by_date' => 'date',
            'approved_by_date' => 'date',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function preparedBySignatureUrl(): ?string
    {
        return $this->prepared_by_signature_path ? Storage::url($this->prepared_by_signature_path) : null;
    }

    public function reviewedBySignatureUrl(): ?string
    {
        return $this->reviewed_by_signature_path ? Storage::url($this->reviewed_by_signature_path) : null;
    }

    public function approvedBySignatureUrl(): ?string
    {
        return $this->approved_by_signature_path ? Storage::url($this->approved_by_signature_path) : null;
    }
}
