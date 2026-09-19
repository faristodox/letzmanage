<?php

namespace App\Console\Commands;

use App\Models\Holiday;
use App\Services\GoogleHolidayCalendarService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncMalaysiaHolidays extends Command
{
    protected $signature = 'holidays:sync {--until=2027-12-31 : Fetch holidays up to this date (Y-m-d)}';

    protected $description = 'Fetch Malaysia public holidays from Google\'s public holiday calendar and store them for the app Calendar. Safe to re-run — some dates start "(tentative)" until confirmed closer to the time.';

    public function handle(GoogleHolidayCalendarService $calendar): int
    {
        $from = Carbon::today();
        $until = Carbon::parse($this->option('until'))->endOfDay();

        $this->info("Fetching Malaysia public holidays from {$from->toDateString()} to {$until->toDateString()}...");

        $holidays = $calendar->fetchMalaysiaPublicHolidays($from, $until);

        foreach ($holidays as $holiday) {
            // A plain where('date', ...) on a 'date'-cast column would compare
            // against the full "Y-m-d H:i:s" string Eloquent actually stores
            // (see Model::fromDateTime()), so an exact-match updateOrCreate()
            // would never find the existing row on some databases (SQLite,
            // notably) and would keep inserting duplicates. whereDate() wraps
            // both sides in a DATE() comparison, so it matches regardless.
            $existing = Holiday::query()
                ->whereDate('date', $holiday['date'])
                ->where('title', $holiday['title'])
                ->first();

            if ($existing) {
                $existing->update(['description' => $holiday['description']]);
            } else {
                Holiday::create($holiday);
            }
        }

        $this->info('Synced '.count($holidays).' public holiday(s).');

        return self::SUCCESS;
    }
}
