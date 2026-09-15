<?php

namespace Tests\Feature\Livewire;

use App\Enums\RoleName;
use App\Livewire\Archive\Index;
use App\Models\ArchivedFile;
use App\Models\Organization;
use App\Models\OrganizationCalendarSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ArchiveIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(Organization $organization): User
    {
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $admin->assignRole(RoleName::Admin->value);

        return $admin;
    }

    public function test_staff_without_permission_is_forbidden(): void
    {
        $organization = Organization::factory()->create();
        $staff = User::factory()->create(['organization_id' => $organization->id]);
        $staff->assignRole(RoleName::Staff->value);

        Livewire::actingAs($staff)
            ->test(Index::class)
            ->assertForbidden();
    }

    public function test_admin_can_upload_a_file(): void
    {
        Http::fake([
            'https://www.googleapis.com/drive/v3/files/*/permissions' => Http::response(['id' => 'permission-1']),
            'https://www.googleapis.com/drive/v3/files' => Http::response(['id' => 'drive-file-1']),
            'https://www.googleapis.com/upload/drive/v3/files/*' => Http::response(['id' => 'drive-file-1']),
        ]);

        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->archiveEnabled()->create();

        Livewire::actingAs($this->admin($organization))
            ->test(Index::class)
            ->assertViewHas('archiveReady', true)
            ->set('file', UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('archived_files', [
            'organization_id' => $organization->id,
            'original_name' => 'report.pdf',
            'drive_file_id' => 'drive-file-1',
        ]);
    }

    public function test_uploading_shows_a_friendly_error_when_archive_is_not_configured(): void
    {
        $organization = Organization::factory()->create();

        Livewire::actingAs($this->admin($organization))
            ->test(Index::class)
            ->assertViewHas('archiveReady', false)
            ->set('file', UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'))
            ->call('save')
            ->assertSet('uploadError', fn ($message) => filled($message));

        $this->assertDatabaseCount('archived_files', 0);
    }

    public function test_admin_can_delete_a_file(): void
    {
        Http::fake(['https://www.googleapis.com/drive/v3/files/drive-file-1' => Http::response([], 204)]);

        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->archiveEnabled()->create();
        $archivedFile = ArchivedFile::factory()->for($organization)->create(['drive_file_id' => 'drive-file-1']);

        Livewire::actingAs($this->admin($organization))
            ->test(Index::class)
            ->call('confirmDelete', $archivedFile->id)
            ->assertSet('confirmingDeleteId', $archivedFile->id)
            ->call('delete');

        $this->assertDatabaseCount('archived_files', 0);
    }

    public function test_admin_can_download_a_file(): void
    {
        Http::fake(['https://www.googleapis.com/drive/v3/files/drive-file-1*' => Http::response('file-contents')]);

        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->archiveEnabled()->create();
        $archivedFile = ArchivedFile::factory()->for($organization)->create([
            'drive_file_id' => 'drive-file-1',
            'original_name' => 'report.pdf',
        ]);

        Livewire::actingAs($this->admin($organization))
            ->test(Index::class)
            ->call('download', $archivedFile->id)
            ->assertFileDownloaded('report.pdf');
    }
}
