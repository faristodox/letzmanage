<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManageMeetings->value);
    }

    public function view(User $user, Meeting $meeting): bool
    {
        return $user->can(PermissionName::ManageMeetings->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ManageMeetings->value);
    }

    public function update(User $user, Meeting $meeting): bool
    {
        return $user->can(PermissionName::ManageMeetings->value);
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return $user->can(PermissionName::ManageMeetings->value);
    }
}
