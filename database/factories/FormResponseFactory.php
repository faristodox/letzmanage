<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\FormResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormResponse>
 */
class FormResponseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'answers' => [],
        ];
    }
}
