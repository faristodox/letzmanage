<?php

namespace Database\Factories;

use App\Enums\CalendarSyncMode;
use App\Models\Organization;
use App\Models\OrganizationCalendarSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationCalendarSetting>
 */
class OrganizationCalendarSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'sync_mode' => CalendarSyncMode::Disabled,
        ];
    }

    public function sharedModeConnected(): static
    {
        return $this->state([
            'sync_mode' => CalendarSyncMode::Shared,
            'google_account_email' => fake()->safeEmail(),
            'google_access_token' => 'ya29.'.fake()->sha256(),
            'google_refresh_token' => '1//'.fake()->sha256(),
            'google_token_expires_at' => now()->addHour(),
            'google_calendar_id' => 'primary',
            'google_connected_at' => now(),
        ]);
    }

    public function individualMode(): static
    {
        return $this->state([
            'sync_mode' => CalendarSyncMode::Individual,
        ]);
    }

    public function archiveEnabled(): static
    {
        return $this->state(['archive_enabled' => true]);
    }
}
