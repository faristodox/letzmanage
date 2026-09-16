<?php

namespace App\Jobs\Concerns;

use App\Contracts\GoogleCalendarCredentialHolder;
use App\Enums\CalendarSyncMode;
use App\Models\Organization;
use App\Models\User;

/**
 * Used by the Event Google Calendar sync job and EventCalendarSyncService:
 * which connected account (if any) a given sync target should push to.
 * Shared mode always resolves to the org's own connection; Individual mode
 * resolves to the given owner's (event creator's) personal connection, if
 * they have one — an unconnected owner (or no owner at all) naturally
 * resolves to null here, not an error, just nothing to sync to.
 */
trait ResolvesGoogleCalendarCredentialHolder
{
    private function resolveCredentialHolder(Organization $organization, ?User $owner): ?GoogleCalendarCredentialHolder
    {
        $setting = $organization->calendarSetting;

        if (! $setting) {
            return null;
        }

        return match ($setting->sync_mode) {
            CalendarSyncMode::Shared => $setting->isSharedModeConfigured() ? $setting : null,
            CalendarSyncMode::Individual => $owner?->googleAccount?->isConnected() ? $owner->googleAccount : null,
            CalendarSyncMode::Disabled => null,
        };
    }
}
