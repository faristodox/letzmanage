<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\WanitaCalendarEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WanitaCalendarEvent>
 */
class WanitaCalendarEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'date' => fake()->dateTimeBetween('now', '+6 months')->format('Y-m-d'),
            'title' => fake()->words(2, true).' (WANITA)',
        ];
    }
}
