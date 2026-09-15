<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationCalendarSetting;
use App\Models\User;
use App\Models\UserGoogleAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');

        $response
            ->assertOk()
            ->assertSeeVolt('profile.update-profile-information-form')
            ->assertSeeVolt('profile.update-password-form')
            ->assertSeeVolt('profile.delete-user-form');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.update-profile-information-form')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->call('updateProfileInformation');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.update-profile-information-form')
            ->set('name', 'Test User')
            ->set('email', $user->email)
            ->call('updateProfileInformation');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.delete-user-form')
            ->set('password', 'wrong-password')
            ->call('deleteUser');

        $component
            ->assertHasErrors('password')
            ->assertNoRedirect();

        $this->assertNotNull($user->fresh());
    }

    public function test_google_calendar_card_is_hidden_when_org_sync_mode_is_not_individual(): void
    {
        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertDontSeeVolt('profile.google-calendar-connection');
    }

    public function test_google_calendar_card_is_shown_when_org_sync_mode_is_individual(): void
    {
        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->individualMode()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSeeVolt('profile.google-calendar-connection');
    }

    public function test_google_calendar_connection_mount_hydrates_a_connected_account(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        UserGoogleAccount::factory()->for($user)->connected()->create([
            'organization_id' => $organization->id,
            'google_account_email' => 'staff@example.com',
        ]);

        $this->actingAs($user);

        Volt::test('profile.google-calendar-connection')
            ->assertSet('isConnected', true)
            ->assertSet('connectedEmail', 'staff@example.com');
    }

    public function test_google_calendar_connection_can_be_disconnected(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        UserGoogleAccount::factory()->for($user)->connected()->create(['organization_id' => $organization->id]);

        $this->actingAs($user);

        Volt::test('profile.google-calendar-connection')
            ->call('disconnect')
            ->assertSet('isConnected', false)
            ->assertSet('connectedEmail', null);

        $this->assertNull(UserGoogleAccount::where('user_id', $user->id)->first());
    }
}
