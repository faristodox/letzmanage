<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spi_members', function (Blueprint $table) {
            $table->string('no_ahli')->unique()->after('id');
            $table->string('nama')->after('no_ahli');
            $table->string('no_kp')->nullable()->after('nama');
            $table->unsignedTinyInteger('umur')->nullable()->after('no_kp');
            $table->string('jantina', 20)->nullable()->after('umur');
            $table->string('kategori', 10)->nullable()->after('jantina');
            $table->string('kawasan')->nullable()->after('kategori');
            $table->string('no_tel', 20)->nullable()->after('kawasan');
            $table->string('level', 5)->after('no_tel')->index();
            $table->timestamp('synced_at')->nullable()->after('level');
        });
    }

    public function down(): void
    {
        Schema::table('spi_members', function (Blueprint $table) {
            $table->dropColumn([
                'no_ahli', 'nama', 'no_kp', 'umur', 'jantina',
                'kategori', 'kawasan', 'no_tel', 'level', 'synced_at',
            ]);
        });
    }
};
