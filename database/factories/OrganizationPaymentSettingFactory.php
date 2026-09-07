<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationPaymentSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationPaymentSetting>
 */
class OrganizationPaymentSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'payment_gateway_enabled' => false,
            'bank_transfer_enabled' => false,
        ];
    }

    public function chipConfigured(): static
    {
        return $this->state([
            'payment_gateway_enabled' => true,
            'chip_brand_id' => fake()->uuid(),
            'chip_secret_key' => 'sk_test_'.fake()->sha256(),
        ]);
    }

    public function bankTransferConfigured(): static
    {
        return $this->state([
            'bank_transfer_enabled' => true,
            'bank_name' => fake()->company().' Bank',
            'bank_account_number' => fake()->bankAccountNumber(),
            'bank_account_holder' => fake()->name(),
        ]);
    }
}
