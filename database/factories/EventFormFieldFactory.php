<?php

namespace Database\Factories;

use App\Enums\EventFormFieldType;
use App\Models\EventForm;
use App\Models\EventFormField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventFormField>
 */
class EventFormFieldFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_form_id' => EventForm::factory(),
            'label' => fake()->words(3, true),
            'type' => EventFormFieldType::Text,
            'required' => false,
            'order' => 0,
        ];
    }

    public function choice(array $options): static
    {
        return $this->state([
            'type' => EventFormFieldType::Select,
            'options' => $options,
        ]);
    }
}
