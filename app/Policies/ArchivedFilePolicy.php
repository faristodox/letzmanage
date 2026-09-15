<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\ArchivedFile;
use App\Models\User;

class ArchivedFilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManageArchive->value);
    }

    public function view(User $user, ArchivedFile $archivedFile): bool
    {
        return $user->can(PermissionName::ManageArchive->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ManageArchive->value);
    }

    public function delete(User $user, ArchivedFile $archivedFile): bool
    {
        return $user->can(PermissionName::ManageArchive->value);
    }
}
