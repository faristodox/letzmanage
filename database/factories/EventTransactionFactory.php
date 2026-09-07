<?php

namespace Database\Factories;

use App\Enums\EventTransactionType;
use App\Models\Event;
use App\Models\EventTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventTransaction>
 */
class EventTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'type' => EventTransactionType::Income,
            'category' => fake()->randomElement(['Registration Fee', 'Sponsorship', 'Venue', 'Catering']),
            'amount' => fake()->randomFloat(2, 10, 500),
            'transaction_date' => now()->format('Y-m-d'),
        ];
    }

    public function expense(): static
    {
        return $this->state(['type' => EventTransactionType::Expense]);
    }
}
