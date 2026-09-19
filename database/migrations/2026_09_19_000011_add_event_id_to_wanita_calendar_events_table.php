<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a synced WANITA sheet entry to the Draft Event auto-created from
 * it — the idempotency key that stops re-syncing from creating a duplicate
 * Event every time (see App\Services\WanitaCalendarSyncService::sync()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wanita_calendar_events', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('title')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wanita_calendar_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });
    }
};
