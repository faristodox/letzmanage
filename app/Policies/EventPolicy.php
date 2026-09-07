<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManageEventForms->value)
            || $user->can(PermissionName::ViewEventResponses->value);
    }

    public function view(User $user, Event $event): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ManageEventForms->value);
    }

    public function update(User $user, Event $event): bool
    {
        return $user->can(PermissionName::ManageEventForms->value);
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->can(PermissionName::ManageEventForms->value);
    }

    /**
     * Deliberately its own policy method (not reusing update()) — for now it
     * maps to the same permission, but this is the seam a future "treasurer"
     * permission plugs into without touching anything else.
     */
    public function viewFinances(User $user, Event $event): bool
    {
        return $this->viewAny($user);
    }

    public function manageFinances(User $user, Event $event): bool
    {
        return $user->can(PermissionName::ManageEventForms->value);
    }
}
