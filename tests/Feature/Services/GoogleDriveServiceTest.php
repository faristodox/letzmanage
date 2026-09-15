<?php

namespace Tests\Feature\Services;

use App\Models\Organization;
use App\Models\OrganizationCalendarSetting;
use App\Services\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GoogleDriveServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_folder_creates_and_persists_the_folder_id_once(): void
    {
        Http::fake(['https://www.googleapis.com/drive/v3/files' => Http::response(['id' => 'folder-123'])]);

        $organization = Organization::factory()->create();
        $setting = OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create();

        $folderId = app(GoogleDriveService::class)->ensureFolder($setting);

        $this->assertSame('folder-123', $folderId);
        $this->assertSame('folder-123', $setting->fresh()->drive_folder_id);
        Http::assertSentCount(1);
    }

    public function test_ensure_folder_reuses_an_already_created_folder(): void
    {
        Http::fake();

        $organization = Organization::factory()->create();
        $setting = OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create([
            'drive_folder_id' => 'existing-folder',
        ]);

        $folderId = app(GoogleDriveService::class)->ensureFolder($setting);

        $this->assertSame('existing-folder', $folderId);
        Http::assertNothingSent();
    }

    public function test_upload_file_creates_metadata_then_attaches_bytes(): void
    {
        Http::fake([
            'https://www.googleapis.com/drive/v3/files' => Http::response(['id' => 'file-456']),
            'https://www.googleapis.com/upload/drive/v3/files/file-456*' => Http::response(['id' => 'file-456']),
        ]);

        $organization = Organization::factory()->create();
        $setting = OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create();

        $fileId = app(GoogleDriveService::class)->uploadFile($setting, 'folder-123', 'report.pdf', 'application/pdf', 'file-bytes');

        $this->assertSame('file-456', $fileId);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://www.googleapis.com/drive/v3/files'
            && $request['name'] === 'report.pdf'
            && $request['parents'] === ['folder-123']);

        Http::assertSent(fn ($request) => $request->method() === 'PATCH'
            && str_contains($request->url(), 'upload/drive/v3/files/file-456')
            && $request->body() === 'file-bytes');
    }

    public function test_download_file_returns_raw_bytes(): void
    {
        Http::fake(['https://www.googleapis.com/drive/v3/files/file-456*' => Http::response('raw-bytes')]);

        $organization = Organization::factory()->create();
        $setting = OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create();

        $contents = app(GoogleDriveService::class)->downloadFile($setting, 'file-456');

        $this->assertSame('raw-bytes', $contents);
        Http::assertSent(fn ($request) => $request['alt'] === 'media');
    }

    public function test_make_publicly_viewable_grants_anyone_reader_access(): void
    {
        Http::fake(['https://www.googleapis.com/drive/v3/files/file-456/permissions' => Http::response(['id' => 'permission-1'])]);

        $organization = Organization::factory()->create();
        $setting = OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create();

        app(GoogleDriveService::class)->makePubliclyViewable($setting, 'file-456');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), 'files/file-456/permissions')
            && $request['role'] === 'reader'
            && $request['type'] === 'anyone');
    }

    public function test_delete_file_treats_a_404_as_already_gone(): void
    {
        Http::fake(['https://www.googleapis.com/drive/v3/files/file-456' => Http::response([], 404)]);

        $organization = Organization::factory()->create();
        $setting = OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create();

        app(GoogleDriveService::class)->deleteFile($setting, 'file-456');

        Http::assertSent(fn ($request) => $request->method() === 'DELETE');
    }

    public function test_a_server_error_throws(): void
    {
        Http::fake(['https://www.googleapis.com/drive/v3/files' => Http::response(['error' => 'boom'], 500)]);

        $organization = Organization::factory()->create();
        $setting = OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create();

        $this->expectException(RuntimeException::class);

        app(GoogleDriveService::class)->ensureFolder($setting);
    }
}
