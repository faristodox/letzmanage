<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('attendance_mode')->default('none')->after('status');
            $table->string('checkin_token')->nullable()->unique()->after('attendance_mode');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['attendance_mode', 'checkin_token']);
        });
    }
};
