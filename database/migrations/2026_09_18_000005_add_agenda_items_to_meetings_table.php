<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structured agenda data (topic/sub-points/responsible-party per item, plus
 * a fallback attendee list) for the official Minutes of Meeting print
 * template — separate from the plain-text `minutes`/`minutes_ms`, which stay
 * exactly as they are for the on-screen view/download.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->longText('agenda_items')->nullable()->after('minutes_ms');
            $table->longText('agenda_items_ms')->nullable()->after('agenda_items');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['agenda_items', 'agenda_items_ms']);
        });
    }
};
