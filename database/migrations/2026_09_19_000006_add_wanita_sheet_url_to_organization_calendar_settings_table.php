<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_calendar_settings', function (Blueprint $table) {
            $table->string('wanita_sheet_url')->nullable()->after('drive_folder_id');
        });
    }

    public function down(): void
    {
        Schema::table('organization_calendar_settings', function (Blueprint $table) {
            $table->dropColumn('wanita_sheet_url');
        });
    }
};
