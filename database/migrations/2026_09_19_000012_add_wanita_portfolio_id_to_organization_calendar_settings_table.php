<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_calendar_settings', function (Blueprint $table) {
            $table->foreignId('wanita_portfolio_id')->nullable()->after('wanita_sheet_url')->constrained('portfolios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organization_calendar_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wanita_portfolio_id');
        });
    }
};
