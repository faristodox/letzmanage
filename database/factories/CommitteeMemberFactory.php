<?php

namespace Database\Factories;

use App\Models\CommitteeMember;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommitteeMember>
 */
class CommitteeMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->name(),
            'position' => fake()->randomElement(['President', 'Vice President', 'Secretary', 'Treasurer', 'Committee Member']),
            'ic_number' => fake()->unique()->numerify('############'),
        ];
    }
}
