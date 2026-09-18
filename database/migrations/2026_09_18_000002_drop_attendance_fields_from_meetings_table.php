<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance check-in moved from Meeting to its linked Event (Committee
 * Meeting type) — see 2026_09_18_000001. Never deployed to production, so a
 * clean drop, no data migration needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The unique index on checkin_token must be dropped in its own
        // Schema::table call, separate from dropping the column itself —
        // SQLite (used in tests) rebuilds the whole table per call, and gets
        // confused if the index and its column disappear in the same pass.
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropUnique(['checkin_token']);
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['attendance_mode', 'checkin_token', 'allow_new_registration']);
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('attendance_mode')->default('none')->after('status');
            $table->string('checkin_token')->nullable()->unique()->after('attendance_mode');
            $table->boolean('allow_new_registration')->default(false)->after('checkin_token');
        });
    }
};
