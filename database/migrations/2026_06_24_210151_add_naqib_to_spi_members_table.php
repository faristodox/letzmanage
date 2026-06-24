<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spi_members', function (Blueprint $table) {
            $table->string('naqib')->nullable()->after('level');
            $table->string('usrah_label')->nullable()->after('naqib');
        });
    }

    public function down(): void
    {
        Schema::table('spi_members', function (Blueprint $table) {
            $table->dropColumn(['naqib', 'usrah_label']);
        });
    }
};
