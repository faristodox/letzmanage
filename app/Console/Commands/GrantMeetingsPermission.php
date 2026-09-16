<?php

namespace App\Console\Commands;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class GrantMeetingsPermission extends Command
{
    protected $signature = 'meetings:grant-permissions';

    protected $description = 'Grant the manage-meetings permission to the admin and manager roles (idempotent backfill for the seeder gotcha)';

    public function handle(): int
    {
        $permission = Permission::findOrCreate(PermissionName::ManageMeetings->value);

        foreach ([RoleName::Admin, RoleName::Manager] as $roleName) {
            $role = Role::where('name', $roleName->value)->first();

            if (! $role) {
                $this->warn("Role '{$roleName->value}' not found, skipping.");

                continue;
            }

            $role->givePermissionTo($permission);
            $this->info("Granted '{$permission->name}' to '{$role->name}'.");
        }

        return self::SUCCESS;
    }
}
