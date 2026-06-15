<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value);
        }

        $admin = Role::findOrCreate(RoleName::Admin->value);
        $admin->syncPermissions(PermissionName::cases());

        $manager = Role::findOrCreate(RoleName::Manager->value);
        $manager->syncPermissions([
            PermissionName::ManageOfficeSpaces,
            PermissionName::CreateBookings,
            PermissionName::ViewOwnBookings,
            PermissionName::ViewAllBookings,
            PermissionName::ApproveBookings,
            PermissionName::CancelAnyBooking,
        ]);

        $staff = Role::findOrCreate(RoleName::Staff->value);
        $staff->syncPermissions([
            PermissionName::CreateBookings,
            PermissionName::ViewOwnBookings,
        ]);
    }
}
