<?php

namespace Tests\Feature\Console;

use App\Models\Holiday;
use App\Services\GoogleHolidayCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncMalaysiaHolidaysTest extends TestCase
{
    use RefreshDatabase;

    public function test_stores_fetched_holidays(): void
    {
        $this->mock(GoogleHolidayCalendarService::class, function ($mock) {
            $mock->shouldReceive('fetchMalaysiaPublicHolidays')
                ->once()
                ->andReturn([
                    ['date' => '2026-11-08', 'title' => 'Deepavali', 'description' => 'Public holiday'],
                    ['date' => '2027-05-01', 'title' => 'Labour Day', 'description' => 'Public holiday'],
                ]);
        });

        $this->artisan('holidays:sync')->assertExitCode(0);

        $this->assertDatabaseCount('holidays', 2);
        $this->assertTrue(Holiday::query()->whereDate('date', '2026-11-08')->where('title', 'Deepavali')->exists());
        $this->assertTrue(Holiday::query()->whereDate('date', '2027-05-01')->where('title', 'Labour Day')->exists());
    }

    public function test_re_running_updates_existing_rows_instead_of_duplicating(): void
    {
        Holiday::create(['date' => '2026-11-08', 'title' => 'Deepavali', 'description' => 'stale']);

        $this->mock(GoogleHolidayCalendarService::class, function ($mock) {
            $mock->shouldReceive('fetchMalaysiaPublicHolidays')
                ->once()
                ->andReturn([
                    ['date' => '2026-11-08', 'title' => 'Deepavali', 'description' => 'Public holiday'],
                ]);
        });

        $this->artisan('holidays:sync')->assertExitCode(0);

        $this->assertDatabaseCount('holidays', 1);
        $this->assertTrue(
            Holiday::query()->whereDate('date', '2026-11-08')
                ->where('title', 'Deepavali')
                ->where('description', 'Public holiday')
                ->exists()
        );
    }

    public function test_until_option_is_passed_through_to_the_service(): void
    {
        $this->mock(GoogleHolidayCalendarService::class, function ($mock) {
            $mock->shouldReceive('fetchMalaysiaPublicHolidays')
                ->once()
                ->withArgs(fn ($from, $until) => $until->format('Y-m-d') === '2026-12-31')
                ->andReturn([]);
        });

        $this->artisan('holidays:sync', ['--until' => '2026-12-31'])->assertExitCode(0);
    }
}
