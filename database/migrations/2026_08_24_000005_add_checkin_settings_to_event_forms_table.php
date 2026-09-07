<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_forms', function (Blueprint $table) {
            $table->boolean('checkin_enabled')->default(false)->after('closes_at');
            $table->dateTime('checkin_starts_at')->nullable()->after('checkin_enabled');
            $table->dateTime('checkin_ends_at')->nullable()->after('checkin_starts_at');
            $table->boolean('checkin_link_enabled')->default(true)->after('checkin_ends_at');
            $table->boolean('checkin_qr_enabled')->default(true)->after('checkin_link_enabled');
            $table->boolean('checkin_manual_enabled')->default(true)->after('checkin_qr_enabled');
            $table->json('checkin_verification_field_ids')->nullable()->after('checkin_manual_enabled');
            $table->string('checkin_verification_mode')->default('all')->after('checkin_verification_field_ids');
            $table->boolean('checkin_onsite_registration_enabled')->default(false)->after('checkin_verification_mode');
        });
    }

    public function down(): void
    {
        Schema::table('event_forms', function (Blueprint $table) {
            $table->dropColumn([
                'checkin_enabled',
                'checkin_starts_at',
                'checkin_ends_at',
                'checkin_link_enabled',
                'checkin_qr_enabled',
                'checkin_manual_enabled',
                'checkin_verification_field_ids',
                'checkin_verification_mode',
                'checkin_onsite_registration_enabled',
            ]);
        });
    }
};
