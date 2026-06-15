<?php

namespace Database\Factories;

use App\Enums\OfficeSpaceStatus;
use App\Models\Branch;
use App\Models\OfficeSpace;
use App\Models\OfficeSpaceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfficeSpace>
 */
class OfficeSpaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => fake()->words(2, true).' Room',
            'type_id' => fn () => OfficeSpaceType::inRandomOrder()->value('id') ?? OfficeSpaceType::factory(),
            'capacity' => fake()->numberBetween(1, 20),
            'facilities' => ['projector', 'whiteboard'],
            'status' => OfficeSpaceStatus::Active,
        ];
    }
}
