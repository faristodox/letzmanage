<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Organization;
use App\Models\OrganizationCalendarSetting;
use App\Models\User;
use App\Models\UserGoogleAccount;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleCalendarConnectionControllerTest extends TestCase
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

    private function fakeTokenExchange(string $email): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token-123',
                'refresh_token' => 'refresh-token-123',
                'expires_in' => 3600,
            ]),
            'https://www.googleapis.com/oauth2/v3/userinfo' => Http::response(['email' => $email]),
        ]);
    }

    public function test_shared_connect_redirects_to_google_with_the_expected_parameters(): void
    {
        config([
            'services.google_calendar.client_id' => 'client-abc',
            'services.google_calendar.redirect_uri_shared' => 'https://app.test/settings/calendar/google/callback',
        ]);

        $organization = Organization::factory()->create();

        $response = $this->actingAs($this->admin($organization))->get(route('settings.calendar.google.connect'));

        $location = $response->headers->get('Location');

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $location);
        $this->assertStringContainsString('client_id=client-abc', $location);
        $this->assertStringContainsString('scope=https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fcalendar.events', $location);
        $this->assertStringContainsString('redirect_uri=https%3A%2F%2Fapp.test%2Fsettings%2Fcalendar%2Fgoogle%2Fcallback', $location);
        $this->assertStringContainsString('access_type=offline', $location);
        $this->assertStringContainsString('prompt=consent', $location);
        $this->assertStringContainsString('state=', $location);
    }

    public function test_shared_callback_with_valid_state_stores_tokens_on_the_organization(): void
    {
        $this->fakeTokenExchange('org-account@example.com');

        $organization = Organization::factory()->create();
        $admin = $this->admin($organization);

        $response = $this->actingAs($admin)
            ->withSession(['google_oauth_state' => 'known-state'])
            ->get(route('settings.calendar.google.callback', ['code' => 'auth-code', 'state' => 'known-state']));

        $response->assertRedirect(route('settings.calendar'));

        $setting = OrganizationCalendarSetting::where('organization_id', $organization->id)->first();
        $this->assertNotNull($setting);
        $this->assertSame('org-account@example.com', $setting->google_account_email);
        $this->assertSame('access-token-123', $setting->google_access_token);
        $this->assertSame('refresh-token-123', $setting->google_refresh_token);
    }

    public function test_shared_callback_with_a_mismatched_state_is_forbidden(): void
    {
        $this->fakeTokenExchange('org-account@example.com');

        $organization = Organization::factory()->create();

        $response = $this->actingAs($this->admin($organization))
            ->withSession(['google_oauth_state' => 'known-state'])
            ->get(route('settings.calendar.google.callback', ['code' => 'auth-code', 'state' => 'wrong-state']));

        $response->assertForbidden();
        $this->assertNull(OrganizationCalendarSetting::where('organization_id', $organization->id)->first());
    }

    public function test_shared_callback_with_no_state_in_session_is_forbidden(): void
    {
        $organization = Organization::factory()->create();

        $response = $this->actingAs($this->admin($organization))
            ->get(route('settings.calendar.google.callback', ['code' => 'auth-code', 'state' => 'anything']));

        $response->assertForbidden();
    }

    public function test_individual_connect_is_forbidden_when_the_org_is_not_in_individual_mode(): void
    {
        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create();

        $staff = User::factory()->create(['organization_id' => $organization->id]);
        $staff->assignRole(RoleName::Staff->value);

        $this->actingAs($staff)
            ->get(route('profile.google-calendar.connect'))
            ->assertForbidden();
    }

    public function test_individual_callback_with_valid_state_stores_tokens_on_the_user(): void
    {
        $this->fakeTokenExchange('staff-account@example.com');

        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->individualMode()->create();

        $staff = User::factory()->create(['organization_id' => $organization->id]);
        $staff->assignRole(RoleName::Staff->value);

        $response = $this->actingAs($staff)
            ->withSession(['google_oauth_state' => 'known-state'])
            ->get(route('profile.google-calendar.callback', ['code' => 'auth-code', 'state' => 'known-state']));

        $response->assertRedirect(route('profile'));

        $account = UserGoogleAccount::where('user_id', $staff->id)->first();
        $this->assertNotNull($account);
        $this->assertSame('staff-account@example.com', $account->google_account_email);
        $this->assertSame('access-token-123', $account->google_access_token);
    }

    public function test_individual_callback_is_forbidden_when_the_org_is_not_in_individual_mode(): void
    {
        $this->fakeTokenExchange('staff-account@example.com');

        $organization = Organization::factory()->create();

        $staff = User::factory()->create(['organization_id' => $organization->id]);
        $staff->assignRole(RoleName::Staff->value);

        $response = $this->actingAs($staff)
            ->withSession(['google_oauth_state' => 'known-state'])
            ->get(route('profile.google-calendar.callback', ['code' => 'auth-code', 'state' => 'known-state']));

        $response->assertForbidden();
        $this->assertNull(UserGoogleAccount::where('user_id', $staff->id)->first());
    }
}
