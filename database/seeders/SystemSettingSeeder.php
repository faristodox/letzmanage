<?php

namespace Database\Seeders;

use App\Enums\ApprovalMode;
use App\Enums\SettingKey;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SystemSetting::firstOrCreate(
            ['branch_id' => null, 'key' => SettingKey::BookingApprovalMode->value],
            ['value' => ApprovalMode::Manual->value]
        );
    }
}
