<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('description');
            $table->string('start_time')->nullable()->after('start_date');
            $table->date('end_date')->nullable()->after('start_time');
            $table->string('end_time')->nullable()->after('end_date');
            $table->string('location')->nullable()->after('end_time');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'start_time', 'end_date', 'end_time', 'location']);
        });
    }
};
