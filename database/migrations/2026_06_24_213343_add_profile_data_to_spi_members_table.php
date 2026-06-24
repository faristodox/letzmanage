<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spi_members', function (Blueprint $table) {
            $table->json('jawatankuasa')->nullable()->after('usrah_label');
            $table->json('usrah_dibawa')->nullable()->after('jawatankuasa');
            $table->json('penglibatan_amal')->nullable()->after('usrah_dibawa');
        });
    }

    public function down(): void
    {
        Schema::table('spi_members', function (Blueprint $table) {
            $table->dropColumn(['jawatankuasa', 'usrah_dibawa', 'penglibatan_amal']);
        });
    }
};
