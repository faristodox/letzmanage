<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\OfficeSpaceType;
use App\Models\User;

class OfficeSpaceTypePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManageSettings->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionName::ManageSettings->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, OfficeSpaceType $officeSpaceType): bool
    {
        return $user->can(PermissionName::ManageSettings->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, OfficeSpaceType $officeSpaceType): bool
    {
        return $user->can(PermissionName::ManageSettings->value);
    }
}
