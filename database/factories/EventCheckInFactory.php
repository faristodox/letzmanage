<?php

namespace Database\Factories;

use App\Enums\EventCheckInMethod;
use App\Models\EventCheckIn;
use App\Models\EventForm;
use App\Models\EventFormResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventCheckIn>
 */
class EventCheckInFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_form_id' => EventForm::factory(),
            'event_form_response_id' => EventFormResponse::factory(),
            'method' => EventCheckInMethod::Manual,
            'checked_in_at' => now(),
        ];
    }
}
