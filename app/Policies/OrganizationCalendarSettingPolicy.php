<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\OrganizationCalendarSetting;
use App\Models\User;

class OrganizationCalendarSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManageSettings->value);
    }

    public function view(User $user, OrganizationCalendarSetting $organizationCalendarSetting): bool
    {
        return $user->can(PermissionName::ManageSettings->value);
    }

    public function update(User $user, OrganizationCalendarSetting $organizationCalendarSetting): bool
    {
        return $user->can(PermissionName::ManageSettings->value);
    }
}
