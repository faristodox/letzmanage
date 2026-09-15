<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_calendar_settings', function (Blueprint $table) {
            $table->boolean('archive_enabled')->default(false)->after('sync_mode');
            $table->string('drive_folder_id')->nullable()->after('google_connected_at');
        });
    }

    public function down(): void
    {
        Schema::table('organization_calendar_settings', function (Blueprint $table) {
            $table->dropColumn(['archive_enabled', 'drive_folder_id']);
        });
    }
};
