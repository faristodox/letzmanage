<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

abstract class TestCase extends BaseTestCase
{
    /**
     * Hard stop against tests ever reaching real external services (Telegram,
     * mail, etc.) — Notification::fake() covers the Laravel Notification
     * system (mail/database/telegram-via-package), and Http::preventStrayRequests()
     * additionally blocks BookingService's raw Http::post() straight to the
     * Telegram Bot API, which bypasses Notifications entirely. A test that
     * needs to assert on either can still call Notification::fake()/Http::fake()
     * itself — this only prevents *unfaked* real sends, not testing them.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Http::preventStrayRequests();
    }
}
