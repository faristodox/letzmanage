<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ConfigureGoogleCalendarRedirectUris extends Command
{
    protected $signature = 'google-calendar:configure-redirect-uris';

    protected $description = 'Append the Google Calendar OAuth redirect-URI env vars to .env if missing, then clear cached config';

    private const LINES = [
        'GOOGLE_CALENDAR_REDIRECT_URI_SHARED' => 'GOOGLE_CALENDAR_REDIRECT_URI_SHARED="${APP_URL}/settings/calendar/google/callback"',
        'GOOGLE_CALENDAR_REDIRECT_URI_INDIVIDUAL' => 'GOOGLE_CALENDAR_REDIRECT_URI_INDIVIDUAL="${APP_URL}/profile/google-calendar/callback"',
    ];

    public function handle(): int
    {
        $envPath = base_path('.env');

        if (! is_writable($envPath)) {
            $this->error('.env is not writable.');

            return self::FAILURE;
        }

        $contents = file_get_contents($envPath);
        $added = [];

        foreach (self::LINES as $key => $line) {
            if (preg_match('/^'.preg_quote($key, '/').'=/m', $contents)) {
                $this->line("{$key} already set, leaving as-is.");

                continue;
            }

            $contents = rtrim($contents, "\n")."\n".$line."\n";
            $added[] = $key;
        }

        if ($added) {
            file_put_contents($envPath, $contents);
            Artisan::call('config:clear');
            $this->info('Added: '.implode(', ', $added).'. Config cache cleared.');
        } else {
            $this->info('Nothing to do, both keys already present.');
        }

        return self::SUCCESS;
    }
}
