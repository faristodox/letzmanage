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
        return $this->viewAny($user) && $this->matchesPortfolio($user, $event);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ManageEventForms->value);
    }

    public function update(User $user, Event $event): bool
    {
        return $user->can(PermissionName::ManageEventForms->value) && $this->matchesPortfolio($user, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->can(PermissionName::ManageEventForms->value) && $this->matchesPortfolio($user, $event);
    }

    /**
     * Deliberately its own policy method (not reusing update()) — for now it
     * maps to the same permission, but this is the seam a future "treasurer"
     * permission plugs into without touching anything else.
     */
    public function viewFinances(User $user, Event $event): bool
    {
        return $this->viewAny($user) && $this->matchesPortfolio($user, $event);
    }

    public function manageFinances(User $user, Event $event): bool
    {
        return $user->can(PermissionName::ManageEventForms->value) && $this->matchesPortfolio($user, $event);
    }

    /**
     * A portfolio-scoped user (a Committee Member account) may only act on
     * their own portfolio's events — Admin/Manager (portfolio_id null) are
     * unrestricted. This closes the gap that query-level filtering in
     * Events\Index alone leaves open: hiding another portfolio's event from
     * the list doesn't stop someone acting on it directly by id/URL.
     */
    private function matchesPortfolio(User $user, Event $event): bool
    {
        return $user->portfolio_id === null || $user->portfolio_id === $event->portfolio_id;
    }
}
