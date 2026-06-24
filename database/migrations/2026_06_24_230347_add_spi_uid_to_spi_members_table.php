<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spi_members', function (Blueprint $table) {
            $table->unsignedInteger('spi_uid')->nullable()->after('no_ahli')->index();
        });
    }

    public function down(): void
    {
        Schema::table('spi_members', function (Blueprint $table) {
            $table->dropColumn('spi_uid');
        });
    }
};
