<?php

namespace App\Contracts;

/**
 * A model that can be pushed to Google Calendar as an event — Booking and
 * Event both implement this. GoogleCalendarService depends only on this
 * interface, not the concrete models, so the same create/update/delete code
 * path serves both. Implementers are expected to be Eloquent models with a
 * `google_event_id` string column (read/written directly, not part of this
 * contract, since it's plain attribute access rather than behavior).
 */
interface GoogleCalendarSyncable
{
    /**
     * @return array{summary: string, description: string, location: ?string, start: array, end: array}
     */
    public function googleEventPayload(): array;
}
