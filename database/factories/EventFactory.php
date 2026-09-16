<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 100000),
            'description' => fake()->optional()->paragraph(),
            'status' => EventStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => EventStatus::Published]);
    }
}
