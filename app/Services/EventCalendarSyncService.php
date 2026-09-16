<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Jobs\Concerns\ResolvesGoogleCalendarCredentialHolder;
use App\Jobs\SyncEventToGoogleCalendarJob;
use App\Models\Event;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps an Event's Google Calendar sync in step with whether it's currently
 * "live": has a start_date and the Event's own status is Published — the
 * registration form's status is a separate concern (whether sign-ups are
 * open) and has no bearing on calendar sync. Call reconcile() after any
 * save that could change either input (the event's status, or its own
 * date/location settings) rather than tracking transitions separately at
 * each call site.
 */
class EventCalendarSyncService
{
    use ResolvesGoogleCalendarCredentialHolder;

    public function __construct(private readonly GoogleCalendarService $calendar) {}

    public function reconcile(Event $event): void
    {
        $isLive = $event->start_date !== null && $event->status === EventStatus::Published;

        if (! $isLive && ! $event->google_event_id) {
            // Nothing synced and nothing to sync — skip the queue round-trip.
            return;
        }

        try {
            SyncEventToGoogleCalendarJob::dispatch($event->organization_id, $event->id, $isLive ? 'upsert' : 'delete');
        } catch (Throwable $e) {
            Log::warning('Failed to queue Google Calendar sync for event: '.$e->getMessage(), ['event_id' => $event->id]);
        }
    }

    /**
     * Delete the synced Google Calendar event synchronously, before the
     * Event row itself is deleted — a job queued after deletion couldn't
     * re-fetch the row to know what to clean up. Event deletion is a rare,
     * manual admin action, so the added latency of one inline API call here
     * is an acceptable trade-off for not having to carry a data snapshot
     * through the queue.
     */
    public function syncOnDelete(Event $event): void
    {
        if (! $event->google_event_id) {
            return;
        }

        $event->loadMissing(['creator.googleAccount', 'organization.calendarSetting']);

        if (! $event->organization) {
            return;
        }

        $holder = $this->resolveCredentialHolder($event->organization, $event->creator);

        if (! $holder) {
            return;
        }

        try {
            $this->calendar->deleteEvent($holder, $event->google_event_id);
        } catch (Throwable $e) {
            Log::warning('Failed to delete Google Calendar event before deleting event: '.$e->getMessage(), ['event_id' => $event->id]);
        }
    }
}
