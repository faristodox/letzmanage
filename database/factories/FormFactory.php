<?php

namespace Database\Factories;

use App\Enums\FormStatus;
use App\Models\Form;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Form>
 */
class FormFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 100000),
            'description' => fake()->optional()->paragraph(),
            'status' => FormStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => FormStatus::Published]);
    }
}
