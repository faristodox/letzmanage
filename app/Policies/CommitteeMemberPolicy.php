<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\CommitteeMember;
use App\Models\User;

class CommitteeMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManageMeetings->value);
    }

    public function view(User $user, CommitteeMember $committeeMember): bool
    {
        return $user->can(PermissionName::ManageMeetings->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ManageMeetings->value);
    }

    public function update(User $user, CommitteeMember $committeeMember): bool
    {
        return $user->can(PermissionName::ManageMeetings->value);
    }

    public function delete(User $user, CommitteeMember $committeeMember): bool
    {
        return $user->can(PermissionName::ManageMeetings->value);
    }
}
