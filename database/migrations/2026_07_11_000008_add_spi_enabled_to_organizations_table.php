<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // "Data Ahli (SPI)" is an IKRAM-specific, confidential module.
            // Off by default; super-admins enable it per organization.
            $table->boolean('spi_enabled')->default(false)->after('status');
        });

        // Keep SPI enabled for the existing IKRAM organization.
        DB::table('organizations')->where('slug', 'ikram-setiawangsa')->update(['spi_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('spi_enabled');
        });
    }
};
