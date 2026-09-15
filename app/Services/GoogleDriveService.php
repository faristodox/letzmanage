<?php

namespace App\Services;

use App\Contracts\GoogleCalendarCredentialHolder;
use App\Models\OrganizationCalendarSetting;
use App\Services\Concerns\AuthenticatesGoogleRequests;
use RuntimeException;

/**
 * File storage against the Google Drive v3 REST API — used by the Archive
 * feature. No SDK, plain HTTP calls, matching GoogleCalendarService's style.
 *
 * Uploads use a two-step "create metadata, then attach bytes" sequence
 * rather than Drive's combined multipart/related upload — Laravel's Http
 * facade builds multipart/form-data via attach(), which isn't the same
 * protocol Drive's single-request multipart upload needs, so two plain
 * calls are simpler and avoid that mismatch. This simple (non-resumable)
 * path is only reliable for files up to ~5MB per Google's own guidance;
 * larger files would need the resumable-upload protocol, not built here.
 */
class GoogleDriveService
{
    use AuthenticatesGoogleRequests;

    private const BASE_URL = 'https://www.googleapis.com/drive/v3/';

    private const UPLOAD_BASE_URL = 'https://www.googleapis.com/upload/drive/v3/';

    public function __construct(private readonly GoogleOAuthService $oauth) {}

    /**
     * Returns the Archive folder's Drive id, creating it (and persisting
     * the id onto the setting) the first time this is called.
     */
    public function ensureFolder(OrganizationCalendarSetting $setting): string
    {
        if ($setting->drive_folder_id) {
            return $setting->drive_folder_id;
        }

        $result = $this->authenticatedClient($setting, $this->oauth, self::BASE_URL)
            ->post('files', [
                'name' => 'Letz Manage Archive',
                'mimeType' => 'application/vnd.google-apps.folder',
            ]);

        if ($result->failed()) {
            throw new RuntimeException('Google Drive folder creation failed: '.$result->body());
        }

        $folderId = $result->json('id');
        $setting->update(['drive_folder_id' => $folderId]);

        return $folderId;
    }

    /**
     * @return string The created file's Google Drive id.
     */
    public function uploadFile(GoogleCalendarCredentialHolder $holder, string $folderId, string $filename, string $mimeType, string $contents): string
    {
        $created = $this->authenticatedClient($holder, $this->oauth, self::BASE_URL)
            ->post('files', [
                'name' => $filename,
                'parents' => [$folderId],
            ]);

        if ($created->failed()) {
            throw new RuntimeException('Google Drive file creation failed: '.$created->body());
        }

        $fileId = $created->json('id');

        $uploaded = $this->authenticatedClient($holder, $this->oauth, self::UPLOAD_BASE_URL)
            ->withBody($contents, $mimeType)
            ->patch("files/{$fileId}?uploadType=media");

        if ($uploaded->failed()) {
            throw new RuntimeException('Google Drive file upload failed: '.$uploaded->body());
        }

        return $fileId;
    }

    /**
     * Grants "anyone with the link" viewer access — called once right after
     * upload so every archived file's share link works for whoever it's
     * sent to, not just the connected Google account. Drive's Permissions
     * API is additive (calling this twice on the same file is harmless).
     */
    public function makePubliclyViewable(GoogleCalendarCredentialHolder $holder, string $driveFileId): void
    {
        $result = $this->authenticatedClient($holder, $this->oauth, self::BASE_URL)
            ->post("files/{$driveFileId}/permissions", [
                'role' => 'reader',
                'type' => 'anyone',
            ]);

        if ($result->failed()) {
            throw new RuntimeException('Google Drive permission update failed: '.$result->body());
        }
    }

    public function downloadFile(GoogleCalendarCredentialHolder $holder, string $driveFileId): string
    {
        $result = $this->authenticatedClient($holder, $this->oauth, self::BASE_URL)
            ->get("files/{$driveFileId}", ['alt' => 'media']);

        if ($result->failed()) {
            throw new RuntimeException('Google Drive file download failed: '.$result->body());
        }

        return $result->body();
    }

    /**
     * A 404 is treated as already-gone, not a failure — the file may have
     * been deleted manually in Google Drive since it was archived.
     */
    public function deleteFile(GoogleCalendarCredentialHolder $holder, string $driveFileId): void
    {
        $result = $this->authenticatedClient($holder, $this->oauth, self::BASE_URL)
            ->delete("files/{$driveFileId}");

        if ($result->failed() && $result->status() !== 404) {
            throw new RuntimeException('Google Drive file deletion failed: '.$result->body());
        }
    }
}
