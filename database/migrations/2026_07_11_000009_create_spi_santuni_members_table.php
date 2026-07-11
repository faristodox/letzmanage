<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spi_santuni_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->string('nama');
            $table->string('no_kp')->nullable();
            $table->string('keahlian')->nullable();
            $table->unsignedTinyInteger('umur')->nullable();
            $table->string('peringkat', 10)->nullable();   // PRKT (e.g. AB)
            $table->string('jantina', 20)->nullable();
            $table->string('kategori', 10)->nullable();     // KTGR
            $table->string('negeri')->nullable();
            $table->string('kawasan')->nullable();
            $table->string('tarikh_semak')->nullable();     // TKHSEMAK-PEJ SUA
            $table->string('tarikh_lulus')->nullable();     // TKHLULUS-JKP
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'no_kp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spi_santuni_members');
    }
};
