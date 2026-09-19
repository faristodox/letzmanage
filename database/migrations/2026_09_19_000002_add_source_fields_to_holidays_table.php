<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds support for a second holiday source (cutisekolah.com.my, see
 * App\Services\CutiSekolahHolidayService) alongside the existing Google
 * public-holiday sync — plus school holidays, which are per-state and can
 * span a date range, unlike the single-day public holidays synced so far.
 * Existing rows are all from the original Google sync, so they default to
 * source=google/type=public, which is exactly what they already are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->date('end_date')->nullable()->after('date');
            $table->string('source')->default('google')->after('description');
            $table->string('type')->default('public')->after('source');
            $table->string('state')->nullable()->after('type');
        });

        Schema::table('holidays', function (Blueprint $table) {
            $table->dropUnique(['date', 'title']);
            $table->unique(['date', 'title', 'source', 'state']);
        });
    }

    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropUnique(['date', 'title', 'source', 'state']);
            $table->unique(['date', 'title']);
        });

        Schema::table('holidays', function (Blueprint $table) {
            $table->dropColumn(['end_date', 'source', 'type', 'state']);
        });
    }
};
