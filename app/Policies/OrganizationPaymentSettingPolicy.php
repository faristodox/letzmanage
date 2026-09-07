<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\OrganizationPaymentSetting;
use App\Models\User;

class OrganizationPaymentSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManageSettings->value);
    }

    public function view(User $user, OrganizationPaymentSetting $organizationPaymentSetting): bool
    {
        return $user->can(PermissionName::ManageSettings->value);
    }

    public function update(User $user, OrganizationPaymentSetting $organizationPaymentSetting): bool
    {
        return $user->can(PermissionName::ManageSettings->value);
    }
}
