<?php

namespace Tests\Feature\Livewire;

use App\Enums\CalendarSyncMode;
use App\Enums\RoleName;
use App\Livewire\Settings\Calendar;
use App\Models\Organization;
use App\Models\OrganizationCalendarSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsCalendarTest extends TestCase
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

    public function test_mount_hydrates_an_existing_shared_mode_connection(): void
    {
        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create([
            'google_account_email' => 'org@example.com',
        ]);

        Livewire::actingAs($this->admin($organization))
            ->test(Calendar::class)
            ->assertSet('syncMode', 'shared')
            ->assertSet('isConnected', true)
            ->assertSet('connectedEmail', 'org@example.com');
    }

    public function test_admin_can_switch_to_individual_mode(): void
    {
        $organization = Organization::factory()->create();

        Livewire::actingAs($this->admin($organization))
            ->test(Calendar::class)
            ->set('syncMode', 'individual')
            ->call('save')
            ->assertHasNoErrors();

        $setting = OrganizationCalendarSetting::where('organization_id', $organization->id)->first();
        $this->assertNotNull($setting);
        $this->assertSame(CalendarSyncMode::Individual, $setting->sync_mode);
    }

    public function test_disconnect_clears_tokens_but_preserves_the_chosen_mode(): void
    {
        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create();

        Livewire::actingAs($this->admin($organization))
            ->test(Calendar::class)
            ->call('disconnect')
            ->assertSet('isConnected', false)
            ->assertSet('connectedEmail', null);

        $setting = OrganizationCalendarSetting::where('organization_id', $organization->id)->first();
        $this->assertSame(CalendarSyncMode::Shared, $setting->sync_mode);
        $this->assertNull($setting->google_access_token);
        $this->assertNull($setting->google_refresh_token);
    }

    public function test_connection_shows_as_connected_even_when_sync_mode_is_disabled(): void
    {
        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->create([
            'google_account_email' => 'org@example.com',
            'google_refresh_token' => 'refresh-token',
        ]);

        Livewire::actingAs($this->admin($organization))
            ->test(Calendar::class)
            ->assertSet('syncMode', 'disabled')
            ->assertSet('isConnected', true);
    }

    public function test_admin_can_enable_archive(): void
    {
        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create();

        Livewire::actingAs($this->admin($organization))
            ->test(Calendar::class)
            ->assertSet('archiveEnabled', false)
            ->set('archiveEnabled', true)
            ->call('save')
            ->assertHasNoErrors();

        $setting = OrganizationCalendarSetting::where('organization_id', $organization->id)->first();
        $this->assertTrue($setting->archive_enabled);
    }

    public function test_disconnect_also_clears_the_drive_folder_id(): void
    {
        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->archiveEnabled()->create([
            'drive_folder_id' => 'folder-123',
        ]);

        Livewire::actingAs($this->admin($organization))
            ->test(Calendar::class)
            ->call('disconnect');

        $setting = OrganizationCalendarSetting::where('organization_id', $organization->id)->first();
        $this->assertNull($setting->drive_folder_id);
        $this->assertTrue($setting->archive_enabled, 'archive_enabled preference should survive a disconnect');
    }

    public function test_staff_cannot_access_calendar_settings(): void
    {
        $organization = Organization::factory()->create();
        $staff = User::factory()->create(['organization_id' => $organization->id]);
        $staff->assignRole(RoleName::Staff->value);

        Livewire::actingAs($staff)
            ->test(Calendar::class)
            ->assertForbidden();
    }
}
