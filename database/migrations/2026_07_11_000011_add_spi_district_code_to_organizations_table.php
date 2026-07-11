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
            // SPI kawasan (memberdistrict) code that scopes this org's SPI data.
            $table->string('spi_district_code', 10)->nullable()->after('spi_enabled');
        });

        // Existing IKRAM Setiawangsa org uses memberdistrict 36.
        DB::table('organizations')->where('slug', 'ikram-setiawangsa')->update(['spi_district_code' => '36']);
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('spi_district_code');
        });
    }
};
