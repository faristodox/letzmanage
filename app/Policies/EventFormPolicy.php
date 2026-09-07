<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\EventForm;
use App\Models\User;

class EventFormPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManageEventForms->value)
            || $user->can(PermissionName::ViewEventResponses->value);
    }

    public function view(User $user, EventForm $eventForm): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ManageEventForms->value);
    }

    public function update(User $user, EventForm $eventForm): bool
    {
        return $user->can(PermissionName::ManageEventForms->value);
    }

    public function delete(User $user, EventForm $eventForm): bool
    {
        return $user->can(PermissionName::ManageEventForms->value);
    }

    public function viewResponses(User $user, EventForm $eventForm): bool
    {
        return $this->viewAny($user);
    }

    public function manageResponses(User $user, EventForm $eventForm): bool
    {
        return $user->can(PermissionName::ManageEventForms->value);
    }

    public function viewCheckIn(User $user, EventForm $eventForm): bool
    {
        return $user->can(PermissionName::ManageEventForms->value)
            || $user->can(PermissionName::CheckInEventParticipants->value)
            || $user->can(PermissionName::ViewEventResponses->value);
    }

    public function manualCheckIn(User $user, EventForm $eventForm): bool
    {
        return $user->can(PermissionName::ManageEventForms->value)
            || $user->can(PermissionName::CheckInEventParticipants->value);
    }

    public function undoCheckIn(User $user, EventForm $eventForm): bool
    {
        return $user->can(PermissionName::ManageEventForms->value);
    }

    /**
     * Deliberately its own policy method (not reusing manageResponses()) —
     * same seam pattern as viewFinances/manageFinances on EventPolicy, in
     * case reviewing payments needs a narrower permission later.
     */
    public function reviewPayments(User $user, EventForm $eventForm): bool
    {
        return $user->can(PermissionName::ManageEventForms->value);
    }
}
