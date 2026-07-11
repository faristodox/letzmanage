<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Default organization (tenant); everything seeded below is stamped to it.
        $organization = Organization::firstOrCreate(
            ['slug' => 'ikram-setiawangsa'],
            ['name' => 'IKRAM Setiawangsa', 'status' => 'active']
        );
        app(CurrentOrganization::class)->set($organization);

        $this->call([
            RolesAndPermissionsSeeder::class,
            BranchSeeder::class,
            SystemSettingSeeder::class,
        ]);

        $headOffice = Branch::where('name', 'Head Office')->first();

        $admin = User::firstOrCreate(
            ['email' => 'admin@letzmanage.test'],
            [
                'name' => 'System Admin',
                'password' => 'password',
                'branch_id' => $headOffice->id,
                'status' => UserStatus::Active,
            ]
        );

        $admin->assignRole(RoleName::Admin->value);
    }
}
