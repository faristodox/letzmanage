<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use App\Models\UserGoogleAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserGoogleAccount>
 */
class UserGoogleAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
        ];
    }

    public function connected(): static
    {
        return $this->state([
            'google_account_email' => fake()->safeEmail(),
            'google_access_token' => 'ya29.'.fake()->sha256(),
            'google_refresh_token' => '1//'.fake()->sha256(),
            'google_token_expires_at' => now()->addHour(),
            'google_calendar_id' => 'primary',
            'google_connected_at' => now(),
        ]);
    }
}
