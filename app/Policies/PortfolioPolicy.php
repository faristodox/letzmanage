<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Portfolio;
use App\Models\User;

class PortfolioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManagePortfolios->value);
    }

    public function view(User $user, Portfolio $portfolio): bool
    {
        return $user->can(PermissionName::ManagePortfolios->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ManagePortfolios->value);
    }

    public function update(User $user, Portfolio $portfolio): bool
    {
        return $user->can(PermissionName::ManagePortfolios->value);
    }

    public function delete(User $user, Portfolio $portfolio): bool
    {
        return $user->can(PermissionName::ManagePortfolios->value);
    }
}
