<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Form;
use App\Models\User;

class FormPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManageForms->value)
            || $user->can(PermissionName::ViewFormResponses->value);
    }

    public function view(User $user, Form $form): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ManageForms->value);
    }

    public function update(User $user, Form $form): bool
    {
        return $user->can(PermissionName::ManageForms->value);
    }

    public function delete(User $user, Form $form): bool
    {
        return $user->can(PermissionName::ManageForms->value);
    }

    public function viewResponses(User $user, Form $form): bool
    {
        return $this->viewAny($user);
    }

    public function manageResponses(User $user, Form $form): bool
    {
        return $user->can(PermissionName::ManageForms->value);
    }
}
