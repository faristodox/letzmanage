<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_report_details', function (Blueprint $table) {
            $table->string('prepared_by_signature_path')->nullable()->after('prepared_by_date');
            $table->string('reviewed_by_signature_path')->nullable()->after('reviewed_by_date');
            $table->string('approved_by_signature_path')->nullable()->after('approved_by_date');
        });
    }

    public function down(): void
    {
        Schema::table('event_report_details', function (Blueprint $table) {
            $table->dropColumn(['prepared_by_signature_path', 'reviewed_by_signature_path', 'approved_by_signature_path']);
        });
    }
};
