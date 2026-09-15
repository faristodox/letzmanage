<?php

namespace App\Services;

use App\Contracts\GoogleCalendarCredentialHolder;
use App\Contracts\GoogleCalendarSyncable;
use App\Services\Concerns\AuthenticatesGoogleRequests;
use RuntimeException;

/**
 * Event CRUD against the Google Calendar v3 REST API. Takes a
 * GoogleCalendarCredentialHolder (not a concrete model) so the same code
 * path serves both an organization's shared account and a staff member's
 * own account, and a GoogleCalendarSyncable (Booking or Event) for what to
 * push. No SDK, plain HTTP calls, matching ChipPaymentService's style.
 */
class GoogleCalendarService
{
    use AuthenticatesGoogleRequests;

    private const BASE_URL = 'https://www.googleapis.com/calendar/v3/';

    public function __construct(private readonly GoogleOAuthService $oauth) {}

    /**
     * @return string The created event's Google id.
     */
    public function createEvent(GoogleCalendarCredentialHolder $holder, GoogleCalendarSyncable $syncable): string
    {
        $result = $this->authenticatedClient($holder, $this->oauth, self::BASE_URL)
            ->post("calendars/{$holder->googleCalendarId()}/events", $syncable->googleEventPayload());

        if ($result->failed()) {
            throw new RuntimeException('Google Calendar event creation failed: '.$result->body());
        }

        return $result->json('id');
    }

    public function updateEvent(GoogleCalendarCredentialHolder $holder, GoogleCalendarSyncable $syncable): void
    {
        $result = $this->authenticatedClient($holder, $this->oauth, self::BASE_URL)
            ->patch("calendars/{$holder->googleCalendarId()}/events/{$syncable->google_event_id}", $syncable->googleEventPayload());

        if ($result->failed()) {
            throw new RuntimeException('Google Calendar event update failed: '.$result->body());
        }
    }

    /**
     * A 404/410 is treated as already-gone, not a failure — the event may
     * have been deleted manually in Google Calendar since it was synced.
     */
    public function deleteEvent(GoogleCalendarCredentialHolder $holder, string $googleEventId): void
    {
        $result = $this->authenticatedClient($holder, $this->oauth, self::BASE_URL)
            ->delete("calendars/{$holder->googleCalendarId()}/events/{$googleEventId}");

        if ($result->failed() && ! in_array($result->status(), [404, 410], true)) {
            throw new RuntimeException('Google Calendar event deletion failed: '.$result->body());
        }
    }
}
