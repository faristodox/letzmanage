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
        return $user->can(PermissionName::ManageMeetings->value) && $this->matchesPortfolio($user, $meeting);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ManageMeetings->value);
    }

    public function update(User $user, Meeting $meeting): bool
    {
        return $user->can(PermissionName::ManageMeetings->value) && $this->matchesPortfolio($user, $meeting);
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return $user->can(PermissionName::ManageMeetings->value) && $this->matchesPortfolio($user, $meeting);
    }

    /**
     * A portfolio-scoped user (a Committee Member account) may only act on
     * meetings linked to one of their own portfolio's events — including a
     * standalone meeting with no event link at all, since it can't be
     * proven to belong to their portfolio either. Admin/Manager
     * (portfolio_id null) are unrestricted. Mirrors EventPolicy's
     * matchesPortfolio() — this closes the same gap that query-level
     * filtering in Meetings\Index alone leaves open.
     */
    private function matchesPortfolio(User $user, Meeting $meeting): bool
    {
        return $user->portfolio_id === null || $meeting->event?->portfolio_id === $user->portfolio_id;
    }
}
