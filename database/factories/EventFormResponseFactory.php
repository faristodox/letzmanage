<?php

namespace Database\Factories;

use App\Models\EventForm;
use App\Models\EventFormResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventFormResponse>
 */
class EventFormResponseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_form_id' => EventForm::factory(),
            'answers' => [],
        ];
    }
}
