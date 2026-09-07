<?php

namespace Database\Factories;

use App\Enums\FormFieldType;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormField>
 */
class FormFieldFactory extends Factory
{
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'label' => fake()->words(3, true),
            'type' => FormFieldType::Text,
            'required' => false,
            'order' => 0,
        ];
    }

    public function choice(array $options): static
    {
        return $this->state([
            'type' => FormFieldType::Select,
            'options' => $options,
        ]);
    }
}
