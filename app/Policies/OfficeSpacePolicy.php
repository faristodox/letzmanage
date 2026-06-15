<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\OfficeSpace;
use App\Models\User;

class OfficeSpacePolicy
{
    /**
     * Any authenticated user may browse office spaces (needed to make bookings).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, OfficeSpace $officeSpace): bool
    {
        return true;
    }

    /**
     * Admins can create spaces in any branch; managers only in their own branch.
     */
    public function create(User $user, ?int $branchId = null): bool
    {
        if (! $user->can(PermissionName::ManageOfficeSpaces->value)) {
            return false;
        }

        if ($user->hasRole(RoleName::Admin->value)) {
            return true;
        }

        return $branchId === null || $user->branch_id === $branchId;
    }

    public function update(User $user, OfficeSpace $officeSpace): bool
    {
        if (! $user->can(PermissionName::ManageOfficeSpaces->value)) {
            return false;
        }

        return $user->hasRole(RoleName::Admin->value) || $user->branch_id === $officeSpace->branch_id;
    }

    public function delete(User $user, OfficeSpace $officeSpace): bool
    {
        return $this->update($user, $officeSpace);
    }
}
