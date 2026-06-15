<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\OfficeSpace;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 day', '+1 month');

        return [
            'branch_id' => Branch::factory(),
            'user_id' => User::factory(),
            'space_id' => OfficeSpace::factory(),
            'title' => fake()->sentence(3),
            'start_time' => $start,
            'end_time' => (clone $start)->modify('+1 hour'),
            'status' => BookingStatus::Pending,
        ];
    }
}
