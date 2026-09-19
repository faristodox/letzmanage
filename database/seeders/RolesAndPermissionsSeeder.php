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
            PermissionName::ViewSpiData,
            PermissionName::ManageEventForms,
            PermissionName::ViewEventResponses,
            PermissionName::CheckInEventParticipants,
            PermissionName::ManageForms,
            PermissionName::ViewFormResponses,
            PermissionName::ManageArchive,
            PermissionName::ManageMeetings,
        ]);

        $staff = Role::findOrCreate(RoleName::Staff->value);
        $staff->syncPermissions([
            PermissionName::CreateBookings,
            PermissionName::ViewOwnBookings,
        ]);

        // Scoped to their own portfolio at the query level (see
        // Events\Index / Meetings\Index) — the permissions themselves are
        // the same ones Manager already has for these two features.
        $committeeMember = Role::findOrCreate(RoleName::CommitteeMember->value);
        $committeeMember->syncPermissions([
            PermissionName::ManageEventForms,
            PermissionName::ManageMeetings,
        ]);
    }
}
