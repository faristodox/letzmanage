<?php

namespace Tests\Feature\Services;

use App\Exceptions\ArchiveNotConfiguredException;
use App\Models\ArchivedFile;
use App\Models\Organization;
use App\Models\OrganizationCalendarSetting;
use App\Models\User;
use App\Services\ArchiveFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ArchiveFileServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_throws_when_no_calendar_setting_row_exists(): void
    {
        $organization = Organization::factory()->create();
        $uploader = User::factory()->create(['organization_id' => $organization->id]);
        $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

        $this->expectException(ArchiveNotConfiguredException::class);

        app(ArchiveFileService::class)->upload($organization, $uploader, $file);
    }

    public function test_upload_throws_when_archive_is_not_enabled(): void
    {
        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create(['archive_enabled' => false]);
        $uploader = User::factory()->create(['organization_id' => $organization->id]);
        $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

        $this->expectException(ArchiveNotConfiguredException::class);

        app(ArchiveFileService::class)->upload($organization, $uploader, $file);
    }

    public function test_upload_throws_when_no_google_account_is_connected(): void
    {
        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->create(['archive_enabled' => true]);
        $uploader = User::factory()->create(['organization_id' => $organization->id]);
        $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

        $this->expectException(ArchiveNotConfiguredException::class);

        app(ArchiveFileService::class)->upload($organization, $uploader, $file);
    }

    public function test_upload_creates_the_folder_uploads_and_records_the_file(): void
    {
        Http::fake([
            'https://www.googleapis.com/drive/v3/files/*/permissions' => Http::response(['id' => 'permission-1']),
            'https://www.googleapis.com/drive/v3/files' => Http::response(['id' => 'drive-file-1']),
            'https://www.googleapis.com/upload/drive/v3/files/*' => Http::response(['id' => 'drive-file-1']),
        ]);

        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->archiveEnabled()->create();
        $uploader = User::factory()->create(['organization_id' => $organization->id]);
        $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

        $archivedFile = app(ArchiveFileService::class)->upload($organization, $uploader, $file);

        $this->assertSame($organization->id, $archivedFile->organization_id);
        $this->assertSame($uploader->id, $archivedFile->created_by);
        $this->assertSame('report.pdf', $archivedFile->original_name);
        $this->assertSame('drive-file-1', $archivedFile->drive_file_id);
        $this->assertDatabaseCount('archived_files', 1);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), 'files/drive-file-1/permissions')
            && $request['role'] === 'reader'
            && $request['type'] === 'anyone');
    }

    public function test_delete_removes_from_drive_and_the_local_record(): void
    {
        Http::fake(['https://www.googleapis.com/drive/v3/files/drive-file-1' => Http::response([], 204)]);

        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->archiveEnabled()->create();
        $archivedFile = ArchivedFile::factory()->for($organization)->create(['drive_file_id' => 'drive-file-1']);

        app(ArchiveFileService::class)->delete($archivedFile);

        Http::assertSent(fn ($request) => $request->method() === 'DELETE');
        $this->assertDatabaseCount('archived_files', 0);
    }

    public function test_download_returns_the_files_bytes(): void
    {
        Http::fake(['https://www.googleapis.com/drive/v3/files/drive-file-1*' => Http::response('file-contents')]);

        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->archiveEnabled()->create();
        $archivedFile = ArchivedFile::factory()->for($organization)->create(['drive_file_id' => 'drive-file-1']);

        $contents = app(ArchiveFileService::class)->download($archivedFile);

        $this->assertSame('file-contents', $contents);
    }
}
