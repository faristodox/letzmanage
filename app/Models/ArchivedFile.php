<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'created_by', 'original_name', 'mime_type', 'size', 'drive_file_id'])]
class ArchivedFile extends Model
{
    use BelongsToOrganization, HasFactory;

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function humanSize(): string
    {
        return match (true) {
            $this->size >= 1024 * 1024 => round($this->size / (1024 * 1024), 1).' MB',
            $this->size >= 1024 => round($this->size / 1024).' KB',
            default => $this->size.' B',
        };
    }

    /**
     * Google Drive's standard file-view URL. Only actually opens for
     * whoever the link is sent to if ArchiveFileService::upload() granted
     * "anyone with the link" access at upload time (files archived before
     * that existed won't be link-shareable until re-uploaded).
     */
    public function driveViewUrl(): string
    {
        return "https://drive.google.com/file/d/{$this->drive_file_id}/view";
    }
}
