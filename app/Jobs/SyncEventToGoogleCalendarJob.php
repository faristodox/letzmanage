<?php

namespace App\Jobs;

use App\Contracts\GoogleCalendarCredentialHolder;
use App\Jobs\Concerns\ResolvesGoogleCalendarCredentialHolder;
use App\Models\Event;
use App\Models\Organization;
use App\Services\GoogleCalendarService;
use App\Support\CurrentOrganization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pushes a published event's schedule to whichever Google Calendar applies
 * (the org's shared calendar in Shared mode, or the event creator's own
 * calendar in Individual mode) — the only synced model now (bookings were
 * removed from Google Calendar sync). Dispatched by
 * EventCalendarSyncService::reconcile() whenever an event's "should it be
 * on the calendar" state might have changed (its own status, schedule, or
 * location edited).
 *
 * Only handles cases where the Event row still exists by the time this
 * runs — a hard delete (Events\Index::delete()) cleans up its Google event
 * synchronously beforehand instead of going through this job, since a
 * queued job dispatched after the row is gone couldn't re-fetch it to know
 * what to remove. See EventCalendarSyncService::syncOnDelete().
 */
class SyncEventToGoogleCalendarJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ResolvesGoogleCalendarCredentialHolder, SerializesModels;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $organizationId,
        public readonly int $eventId,
        public readonly string $action, // 'upsert' | 'delete'
    ) {}

    public function handle(CurrentOrganization $currentOrganization, GoogleCalendarService $calendar): void
    {
        $organization = Organization::find($this->organizationId);

        if (! $organization) {
            return;
        }

        $currentOrganization->runFor($organization, function () use ($organization, $calendar): void {
            $event = Event::with('creator.googleAccount')->find($this->eventId);

            if (! $event) {
                return;
            }

            $holder = $this->resolveCredentialHolder($organization, $event->creator);

            if (! $holder) {
                return;
            }

            match ($this->action) {
                'upsert' => $this->upsert($calendar, $holder, $event),
                'delete' => $this->delete($calendar, $holder, $event),
            };
        });
    }

    private function upsert(GoogleCalendarService $calendar, GoogleCalendarCredentialHolder $holder, Event $event): void
    {
        if ($event->google_event_id) {
            $calendar->updateEvent($holder, $event);

            return;
        }

        $event->update(['google_event_id' => $calendar->createEvent($holder, $event)]);
    }

    private function delete(GoogleCalendarService $calendar, GoogleCalendarCredentialHolder $holder, Event $event): void
    {
        if (! $event->google_event_id) {
            return;
        }

        $calendar->deleteEvent($holder, $event->google_event_id);
        $event->update(['google_event_id' => null]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('Google Calendar sync job failed permanently: '.$e->getMessage(), [
            'organization_id' => $this->organizationId,
            'event_id' => $this->eventId,
            'action' => $this->action,
        ]);
    }
}
