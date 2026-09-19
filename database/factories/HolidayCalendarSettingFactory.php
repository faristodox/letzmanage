<?php

namespace Database\Factories;

use App\Enums\HolidaySource;
use App\Models\HolidayCalendarSetting;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HolidayCalendarSetting>
 */
class HolidayCalendarSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'source' => HolidaySource::Google,
        ];
    }

    public function otherSource(string $state): static
    {
        return $this->state([
            'source' => HolidaySource::CutiSekolah,
            'state' => $state,
        ]);
    }
}
