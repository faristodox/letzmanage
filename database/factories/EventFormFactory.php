<?php

namespace Database\Factories;

use App\Enums\CheckInVerificationMode;
use App\Enums\EventFormStatus;
use App\Enums\EventFormType;
use App\Models\Event;
use App\Models\EventForm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventForm>
 */
class EventFormFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'type' => EventFormType::Registration,
            'status' => EventFormStatus::Draft,
            'checkin_enabled' => false,
            'checkin_link_enabled' => true,
            'checkin_qr_enabled' => true,
            'checkin_manual_enabled' => true,
            'checkin_verification_mode' => CheckInVerificationMode::All,
            'checkin_onsite_registration_enabled' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => EventFormStatus::Published]);
    }

    public function checkinEnabled(array $overrides = []): static
    {
        return $this->state(['checkin_enabled' => true, ...$overrides]);
    }

    public function feedback(): static
    {
        return $this->state(['type' => EventFormType::Feedback]);
    }
}
