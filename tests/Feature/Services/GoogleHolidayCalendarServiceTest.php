<?php

namespace Tests\Feature\Services;

use App\Services\GoogleHolidayCalendarService;
use App\Services\GoogleServiceAccountAuthService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GoogleHolidayCalendarServiceTest extends TestCase
{
    private function serviceWithFakeToken(): GoogleHolidayCalendarService
    {
        $auth = Mockery::mock(GoogleServiceAccountAuthService::class);
        $auth->shouldReceive('getAccessToken')
            ->once()
            ->with('https://www.googleapis.com/auth/calendar.readonly')
            ->andReturn('fake-token');

        return new GoogleHolidayCalendarService($auth);
    }

    public function test_keeps_genuine_public_holidays_and_drops_non_holiday_observances(): void
    {
        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/*' => Http::response([
                'items' => [
                    [
                        'summary' => 'Hari Raya Puasa',
                        'description' => 'Public holiday',
                        'start' => ['date' => '2026-11-08'],
                    ],
                    [
                        'summary' => "Valentine's Day",
                        'description' => "Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Malaysia",
                        'start' => ['date' => '2026-02-14'],
                    ],
                    [
                        'summary' => 'Federal Territory Day',
                        'description' => 'Public holiday in Kuala Lumpur, Labuan, Putrajaya',
                        'start' => ['date' => '2027-02-01'],
                    ],
                ],
            ]),
        ]);

        $holidays = $this->serviceWithFakeToken()->fetchMalaysiaPublicHolidays(
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2027-12-31'),
        );

        $this->assertCount(2, $holidays);
        $this->assertSame('Hari Raya Puasa', $holidays[0]['title']);
        $this->assertSame('Federal Territory Day', $holidays[1]['title']);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer fake-token'));
    }

    public function test_follows_pagination_until_no_next_page_token(): void
    {
        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/*' => Http::sequence()
                ->push([
                    'items' => [
                        ['summary' => 'Labour Day', 'description' => 'Public holiday', 'start' => ['date' => '2027-05-01']],
                    ],
                    'nextPageToken' => 'page-2',
                ])
                ->push([
                    'items' => [
                        ['summary' => 'Malaysia Day', 'description' => 'Public holiday', 'start' => ['date' => '2027-09-16']],
                    ],
                ]),
        ]);

        $holidays = $this->serviceWithFakeToken()->fetchMalaysiaPublicHolidays(
            CarbonImmutable::parse('2027-01-01'),
            CarbonImmutable::parse('2027-12-31'),
        );

        $this->assertCount(2, $holidays);
        Http::assertSentCount(2);
    }

    public function test_throws_when_the_calendar_request_fails(): void
    {
        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/*' => Http::response(['error' => 'forbidden'], 403),
        ]);

        $this->expectException(RuntimeException::class);

        $this->serviceWithFakeToken()->fetchMalaysiaPublicHolidays(
            CarbonImmutable::parse('2027-01-01'),
            CarbonImmutable::parse('2027-12-31'),
        );
    }
}
