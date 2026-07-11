<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spi_santuni_members', function (Blueprint $table) {
            // Not on the santuni report itself — filled by cross-referencing the
            // member list (same IC) at sync time.
            $table->string('no_tel', 30)->nullable()->after('kawasan');
        });
    }

    public function down(): void
    {
        Schema::table('spi_santuni_members', function (Blueprint $table) {
            $table->dropColumn('no_tel');
        });
    }
};
