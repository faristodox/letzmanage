<?php

namespace Tests\Feature\Livewire;

use App\Enums\CalendarSyncMode;
use App\Enums\RoleName;
use App\Livewire\Settings\Calendar;
use App\Models\HolidayCalendarSetting;
use App\Models\Organization;
use App\Models\OrganizationCalendarSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_the_full_settings_page_renders_with_the_holiday_calendar_card(): void
    {
        $organization = Organization::factory()->create();

        $this->actingAs($this->admin($organization))
            ->get(route('settings.calendar'))
            ->assertOk()
            ->assertSee('Holiday Calendar')
            ->assertSee('Google Calendar')
            ->assertSee('Other Source');
    }

    public function test_the_holiday_calendar_card_shows_a_single_button_labeled_by_source(): void
    {
        $organization = Organization::factory()->create();

        $component = Livewire::actingAs($this->admin($organization))
            ->test(Calendar::class)
            ->assertSet('holidaySource', 'google');

        // Assert on the fallback span's rendered CONTENT, not the whole HTML
        // blob — the x-text attribute value is a ternary expression that
        // always contains both possible outcomes as literal text.
        $this->assertStringContainsString('>Save</span>', $component->html());
        $this->assertStringNotContainsString('>Save &amp; Sync</span>', $component->html());

        $component->set('holidaySource', 'cutisekolah');

        $this->assertStringContainsString('>Save &amp; Sync</span>', $component->html());
        $this->assertStringNotContainsString('>Save</span>', $component->html());
    }

    public function test_mount_defaults_holiday_source_to_google_when_unconfigured(): void
    {
        $organization = Organization::factory()->create();

        Livewire::actingAs($this->admin($organization))
            ->test(Calendar::class)
            ->assertSet('holidaySource', 'google')
            ->assertSet('holidayState', null);
    }

    public function test_mount_hydrates_an_existing_other_source_holiday_setting(): void
    {
        $organization = Organization::factory()->create();
        HolidayCalendarSetting::factory()->for($organization)->otherSource('selangor')->create();

        Livewire::actingAs($this->admin($organization))
            ->test(Calendar::class)
            ->assertSet('holidaySource', 'cutisekolah')
            ->assertSet('holidayState', 'selangor');
    }

    public function test_admin_can_save_holiday_settings_without_syncing(): void
    {
        $organization = Organization::factory()->create();

        Livewire::actingAs($this->admin($organization))
            ->test(Calendar::class)
            ->set('holidaySource', 'cutisekolah')
            ->set('holidayState', 'kuala-lumpur')
            ->call('saveHolidaySettings')
            ->assertHasNoErrors();

        $setting = HolidayCalendarSetting::where('organization_id', $organization->id)->first();
        $this->assertNotNull($setting);
        $this->assertSame('cutisekolah', $setting->source->value);
        $this->assertSame('kuala-lumpur', $setting->state);

        Http::assertNothingSent();
    }

    public function test_state_is_required_when_choosing_other_source(): void
    {
        $organization = Organization::factory()->create();

        Livewire::actingAs($this->admin($organization))
            ->test(Calendar::class)
            ->set('holidaySource', 'cutisekolah')
            ->set('holidayState', '')
            ->call('saveHolidaySettings')
            ->assertHasErrors('holidayState');
    }

    public function test_state_is_cleared_when_switching_back_to_google(): void
    {
        $organization = Organization::factory()->create();
        HolidayCalendarSetting::factory()->for($organization)->otherSource('selangor')->create();

        Livewire::actingAs($this->admin($organization))
            ->test(Calendar::class)
            ->set('holidaySource', 'google')
            ->call('saveHolidaySettings')
            ->assertHasNoErrors();

        $setting = HolidayCalendarSetting::where('organization_id', $organization->id)->first();
        $this->assertSame('google', $setting->source->value);
        $this->assertNull($setting->state);
    }

    public function test_sync_calls_the_cutisekolah_service_only_for_other_source(): void
    {
        $organization = Organization::factory()->create();

        Http::fake([
            'https://cutisekolah.com.my/kalendar-2026/' => Http::response('<html></html>'),
            'https://cutisekolah.com.my/kalendar-akademik-2026/kuala-lumpur/' => Http::response('<html></html>'),
            'https://cutisekolah.com.my/kalendar-2027/' => Http::response('', 404),
        ]);

        Livewire::actingAs($this->admin($organization))
            ->test(Calendar::class)
            ->set('holidaySource', 'cutisekolah')
            ->set('holidayState', 'kuala-lumpur')
            ->call('syncHolidays')
            ->assertHasNoErrors();

        Http::assertSent(fn ($request) => $request->url() === 'https://cutisekolah.com.my/kalendar-2026/');
    }

    public function test_sync_does_nothing_over_http_when_source_is_google(): void
    {
        $organization = Organization::factory()->create();

        Livewire::actingAs($this->admin($organization))
            ->test(Calendar::class)
            ->set('holidaySource', 'google')
            ->call('syncHolidays')
            ->assertHasNoErrors();

        Http::assertNothingSent();
    }
}
