<?php

namespace Database\Factories;

use App\Models\ArchivedFile;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArchivedFile>
 */
class ArchivedFileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1024, 1024 * 1024),
            'drive_file_id' => 'drive-'.fake()->uuid(),
        ];
    }
}
