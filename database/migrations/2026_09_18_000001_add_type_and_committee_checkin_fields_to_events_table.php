<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Committee Meeting" check-in was previously being built on Meeting itself
 * (never deployed) — moved here so a Meeting's linked Event carries the
 * single, unified check-in mechanism instead of each having its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('type')->default('event')->after('organization_id');
            $table->boolean('checkin_enabled')->default(false)->after('google_event_id');
            $table->string('checkin_token')->nullable()->unique()->after('checkin_enabled');
            $table->boolean('allow_new_registration')->default(false)->after('checkin_token');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['type', 'checkin_enabled', 'checkin_token', 'allow_new_registration']);
        });
    }
};
