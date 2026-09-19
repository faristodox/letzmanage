<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads Google's public "Holidays in Malaysia" calendar — reuses the
 * existing Speech-to-Text service account (see
 * GoogleServiceAccountAuthService), just with the Calendar-specific OAuth
 * scope, since this is a public calendar and needs no per-organization
 * Google connection or sharing. Confirmed against the real API: a plain
 * API key is rejected outright (Calendar API requires an OAuth-style
 * credential), and the cloud-platform scope already used for Speech-to-Text
 * isn't enough on its own — calendar.readonly is required.
 */
class GoogleHolidayCalendarService
{
    private const CALENDAR_ID = 'en.malaysia#holiday@group.v.calendar.google.com';

    private const SCOPE = 'https://www.googleapis.com/auth/calendar.readonly';

    private const BASE_URL = 'https://www.googleapis.com/calendar/v3/';

    public function __construct(private readonly GoogleServiceAccountAuthService $auth) {}

    /**
     * Google's calendar mixes genuine public holidays with non-holiday
     * observances (e.g. Valentine's Day) — its own description field
     * reliably distinguishes them ("Public holiday[...]" vs "Observance"),
     * confirmed against the real API rather than guessed.
     *
     * @return array<int, array{date: string, title: string, description: string}>
     */
    public function fetchMalaysiaPublicHolidays(\DateTimeInterface $from, \DateTimeInterface $until): array
    {
        $token = $this->auth->getAccessToken(self::SCOPE);
        $calendarId = rawurlencode(self::CALENDAR_ID);

        $holidays = [];
        $pageToken = null;

        do {
            $result = Http::withToken($token)->timeout(30)
                ->get(self::BASE_URL."calendars/{$calendarId}/events", array_filter([
                    'timeMin' => $from->format('Y-m-d\TH:i:s\Z'),
                    'timeMax' => $until->format('Y-m-d\TH:i:s\Z'),
                    'maxResults' => 250,
                    'singleEvents' => 'true',
                    'pageToken' => $pageToken,
                ]));

            if ($result->failed()) {
                throw new RuntimeException('Google Calendar request failed: '.$result->body());
            }

            foreach ($result->json('items', []) as $item) {
                $description = (string) ($item['description'] ?? '');
                $date = $item['start']['date'] ?? null;

                if (! $date || ! str_starts_with($description, 'Public holiday')) {
                    continue;
                }

                $holidays[] = [
                    'date' => $date,
                    'title' => (string) ($item['summary'] ?? 'Public Holiday'),
                    'description' => $description,
                ];
            }

            $pageToken = $result->json('nextPageToken');
        } while ($pageToken);

        return $holidays;
    }
}
