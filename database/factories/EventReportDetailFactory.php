<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventReportDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventReportDetail>
 */
class EventReportDetailFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'event_date' => now()->format('Y-m-d'),
            'event_time' => '9:00 AM - 1:00 PM',
            'theme' => fake()->sentence(3),
            'venue' => fake()->address(),
            'objectives' => fake()->paragraph(),
        ];
    }
}
