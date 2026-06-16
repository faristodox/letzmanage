<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:set-webhook';

    protected $description = 'Register the Telegram webhook URL with the Bot API';

    public function handle(): int
    {
        $token = config('services.telegram.token');

        if (! $token) {
            $this->error('services.telegram.token is not configured.');

            return self::FAILURE;
        }

        $url = config('app.url').'/telegram/webhook';

        $response = Http::post("https://api.telegram.org/bot{$token}/setWebhook", [
            'url' => $url,
        ]);

        $json = $response->json();

        if ($json['ok'] ?? false) {
            $this->info("Webhook registered: {$url}");

            return self::SUCCESS;
        }

        $this->error('Failed: '.($json['description'] ?? 'unknown error'));

        return self::FAILURE;
    }
}
