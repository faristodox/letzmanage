<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventReportItineraryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventReportItineraryItem>
 */
class EventReportItineraryItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'time' => '9:00 AM',
            'activity' => fake()->sentence(4),
            'order' => 0,
        ];
    }
}
