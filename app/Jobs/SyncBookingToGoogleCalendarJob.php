<?php

namespace App\Jobs;

use App\Contracts\GoogleCalendarCredentialHolder;
use App\Jobs\Concerns\ResolvesGoogleCalendarCredentialHolder;
use App\Models\Booking;
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
 * Pushes one booking's Approved/rejected/cancelled state to whichever
 * Google Calendar applies for it — the org's shared calendar (Shared mode)
 * or the requester's own calendar (Individual mode). The first queued job
 * in this codebase: a worker starts with no tenant context, so the whole
 * body runs inside CurrentOrganization::runFor(), exactly what that
 * method's doc comment says it's for.
 *
 * Google API failures are allowed to throw (not swallowed here) so `tries`/
 * `backoff` can retry transient failures; only a fully exhausted retry
 * ends up logged via failed().
 */
class SyncBookingToGoogleCalendarJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ResolvesGoogleCalendarCredentialHolder, SerializesModels;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $organizationId,
        public readonly int $bookingId,
        public readonly string $action, // 'upsert' | 'delete'
    ) {}

    public function handle(CurrentOrganization $currentOrganization, GoogleCalendarService $calendar): void
    {
        $organization = Organization::find($this->organizationId);

        if (! $organization) {
            return;
        }

        $currentOrganization->runFor($organization, function () use ($organization, $calendar): void {
            $booking = Booking::with(['user.googleAccount', 'space'])->find($this->bookingId);

            if (! $booking) {
                return;
            }

            $holder = $this->resolveCredentialHolder($organization, $booking->user);

            if (! $holder) {
                return;
            }

            match ($this->action) {
                'upsert' => $this->upsert($calendar, $holder, $booking),
                'delete' => $this->delete($calendar, $holder, $booking),
            };
        });
    }

    private function upsert(GoogleCalendarService $calendar, GoogleCalendarCredentialHolder $holder, Booking $booking): void
    {
        if ($booking->google_event_id) {
            $calendar->updateEvent($holder, $booking);

            return;
        }

        $booking->update(['google_event_id' => $calendar->createEvent($holder, $booking)]);
    }

    private function delete(GoogleCalendarService $calendar, GoogleCalendarCredentialHolder $holder, Booking $booking): void
    {
        if (! $booking->google_event_id) {
            return;
        }

        $calendar->deleteEvent($holder, $booking->google_event_id);
        $booking->update(['google_event_id' => null]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('Google Calendar sync job failed permanently: '.$e->getMessage(), [
            'organization_id' => $this->organizationId,
            'booking_id' => $this->bookingId,
            'action' => $this->action,
        ]);
    }
}
