<?php

namespace App\Services;

use App\Exceptions\ArchiveNotConfiguredException;
use App\Models\ArchivedFile;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Keeps App\Livewire\Archive\Index thin — resolves the org's connected
 * Google account, ensures the Archive folder exists, and moves file bytes
 * to/from Google Drive around the local ArchivedFile record.
 */
class ArchiveFileService
{
    public function __construct(private readonly GoogleDriveService $drive) {}

    public function upload(Organization $organization, User $uploader, UploadedFile $file): ArchivedFile
    {
        $setting = $organization->calendarSetting;

        if (! $setting || ! $setting->isArchiveReady()) {
            throw new ArchiveNotConfiguredException;
        }

        $folderId = $this->drive->ensureFolder($setting);

        $driveFileId = $this->drive->uploadFile(
            $setting,
            $folderId,
            $file->getClientOriginalName(),
            $file->getMimeType() ?: 'application/octet-stream',
            file_get_contents($file->getRealPath())
        );

        // Makes the file's share link work for whoever it's sent to, not
        // just the connected Google account — confirmed with the user as
        // the intended behavior for Archive's "copy link" action.
        $this->drive->makePubliclyViewable($setting, $driveFileId);

        return ArchivedFile::create([
            'organization_id' => $organization->id,
            'created_by' => $uploader->id,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'drive_file_id' => $driveFileId,
        ]);
    }

    /**
     * Deletes from Drive on a best-effort basis — if the org's Google
     * connection is gone, the local record is still removed rather than
     * leaving an un-deletable row stuck in the list.
     */
    public function delete(ArchivedFile $archivedFile): void
    {
        $setting = $archivedFile->organization->calendarSetting;

        if ($setting && $setting->hasConnectedAccount()) {
            $this->drive->deleteFile($setting, $archivedFile->drive_file_id);
        }

        $archivedFile->delete();
    }

    public function download(ArchivedFile $archivedFile): string
    {
        $setting = $archivedFile->organization->calendarSetting;

        if (! $setting || ! $setting->hasConnectedAccount()) {
            throw new ArchiveNotConfiguredException;
        }

        return $this->drive->downloadFile($setting, $archivedFile->drive_file_id);
    }
}
