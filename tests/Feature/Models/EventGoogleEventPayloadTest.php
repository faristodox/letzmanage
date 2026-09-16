<?php

namespace Tests\Feature\Models;

use App\Models\Event;
use Tests\TestCase;

class EventGoogleEventPayloadTest extends TestCase
{
    /**
     * Regression test: an event like "8:30 PM – 12:00 (noon)" with no
     * explicit end_date used to send Google an end time before the start,
     * which the API rejects with "timeRangeEmpty" — the sync job then fails
     * silently from the user's point of view (event just never appears).
     */
    public function test_end_time_at_or_before_start_time_rolls_to_the_next_day(): void
    {
        $event = Event::factory()->make([
            'title' => 'Usrah',
            'start_date' => '2026-09-22',
            'start_time' => '20:30',
            'end_date' => null,
            'end_time' => '12:00',
        ]);

        $payload = $event->googleEventPayload();

        $this->assertSame('2026-09-22T20:30:00+08:00', $payload['start']['dateTime']);
        $this->assertSame('2026-09-23T12:00:00+08:00', $payload['end']['dateTime']);
    }

    public function test_end_time_equal_to_start_time_also_rolls_to_the_next_day(): void
    {
        $event = Event::factory()->make([
            'title' => 'Overnight Camp',
            'start_date' => '2026-09-22',
            'start_time' => '20:30',
            'end_date' => null,
            'end_time' => '20:30',
        ]);

        $payload = $event->googleEventPayload();

        $this->assertSame('2026-09-23T20:30:00+08:00', $payload['end']['dateTime']);
    }

    public function test_normal_same_day_end_time_is_unaffected(): void
    {
        $event = Event::factory()->make([
            'title' => 'Meeting',
            'start_date' => '2026-09-22',
            'start_time' => '09:00',
            'end_date' => null,
            'end_time' => '10:30',
        ]);

        $payload = $event->googleEventPayload();

        $this->assertSame('2026-09-22T09:00:00+08:00', $payload['start']['dateTime']);
        $this->assertSame('2026-09-22T10:30:00+08:00', $payload['end']['dateTime']);
    }

    public function test_explicit_end_date_is_still_respected(): void
    {
        $event = Event::factory()->make([
            'title' => 'Multi-day Retreat',
            'start_date' => '2026-09-22',
            'start_time' => '20:30',
            'end_date' => '2026-09-24',
            'end_time' => '12:00',
        ]);

        $payload = $event->googleEventPayload();

        $this->assertSame('2026-09-24T12:00:00+08:00', $payload['end']['dateTime']);
    }
}
