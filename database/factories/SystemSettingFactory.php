<?php

namespace Database\Factories;

use App\Enums\ApprovalMode;
use App\Enums\SettingKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\SystemSetting>
 */
class SystemSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => null,
            'key' => SettingKey::BookingApprovalMode->value,
            'value' => ApprovalMode::Manual->value,
        ];
    }
}
