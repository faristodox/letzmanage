<?php

namespace Database\Seeders;

use App\Enums\BranchStatus;
use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Branch::firstOrCreate(
            ['name' => 'Head Office'],
            ['location' => 'Kuala Lumpur', 'status' => BranchStatus::Active]
        );
    }
}
