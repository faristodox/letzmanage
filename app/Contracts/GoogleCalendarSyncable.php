<?php

namespace App\Contracts;

/**
 * A model that can be pushed to Google Calendar as an event — Event is the
 * only implementer (Booking sync was removed; GoogleCalendarService still
 * depends only on this interface, not the concrete model, so a future
 * syncable model needs no changes there). Implementers are expected to be
 * Eloquent models with a `google_event_id` string column (read/written
 * directly, not part of this contract, since it's plain attribute access
 * rather than behavior).
 */
interface GoogleCalendarSyncable
{
    /**
     * @return array{summary: string, description: string, location: ?string, start: array, end: array}
     */
    public function googleEventPayload(): array;
}
