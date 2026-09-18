<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual overrides for the print template's signature blocks — null means
 * "use MeetingMinutesPrintService's computed default" (creator's name /
 * whoever holds a Setiausaha position), so most meetings never need these
 * set at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('prepared_by_name')->nullable()->after('agenda_items_ms');
            $table->string('prepared_by_position')->nullable()->after('prepared_by_name');
            $table->string('confirmed_by_name')->nullable()->after('prepared_by_position');
            $table->string('confirmed_by_position')->nullable()->after('confirmed_by_name');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['prepared_by_name', 'prepared_by_position', 'confirmed_by_name', 'confirmed_by_position']);
        });
    }
};
