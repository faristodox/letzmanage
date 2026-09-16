<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Organization;
use App\Services\EventCalendarSyncService;
use App\Support\CurrentOrganization;
use Illuminate\Console\Command;

/**
 * Sync is normally event-driven (fires on event-publish), so anything
 * already Published before a Google account was connected — or while the
 * queue worker wasn't running — never got synced. This dispatches sync for
 * exactly those already-eligible-but-unsynced events; already-synced ones
 * (`google_event_id` set) are skipped, so it's safe to run repeatedly.
 */
class BackfillGoogleCalendarSync extends Command
{
    protected $signature = 'google-calendar:backfill-sync';

    protected $description = 'Sync already-published events that predate a Google account connection';

    public function handle(CurrentOrganization $currentOrganization, EventCalendarSyncService $eventSync): int
    {
        foreach (Organization::all() as $organization) {
            $currentOrganization->runFor($organization, function () use ($organization, $eventSync): void {
                $events = Event::whereNotNull('start_date')->whereNull('google_event_id')->get();

                foreach ($events as $event) {
                    $eventSync->reconcile($event);
                }

                if ($events->isNotEmpty()) {
                    $this->info("[{$organization->name}] reconciled {$events->count()} event(s).");
                }
            });
        }

        return self::SUCCESS;
    }
}
