<?php

namespace Database\Factories;

use App\Enums\EventPaymentMethod;
use App\Enums\EventPaymentStatus;
use App\Models\EventFormResponse;
use App\Models\EventPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventPayment>
 */
class EventPaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_form_response_id' => EventFormResponse::factory(),
            'method' => EventPaymentMethod::Chip,
            'amount' => fake()->randomFloat(2, 10, 500),
            'currency' => 'MYR',
            'status' => EventPaymentStatus::Pending,
        ];
    }

    public function paid(): static
    {
        return $this->state(['status' => EventPaymentStatus::Paid, 'paid_at' => now()]);
    }

    public function bankTransfer(): static
    {
        return $this->state(['method' => EventPaymentMethod::BankTransfer, 'receipt_path' => 'payment-receipts/example.jpg']);
    }
}
