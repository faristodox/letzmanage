<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spi_naqib_usrah', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('usrah_id')->nullable(); // SPI's own u_id, for stable upserts
            $table->string('level', 5)->nullable()->index();
            $table->string('usrah_name')->nullable();
            $table->string('status', 20)->nullable();
            $table->string('naqib_name')->nullable();
            $table->boolean('is_temporary_group')->default(false);
            $table->string('jenis', 20)->nullable();
            $table->string('kategori', 20)->nullable();
            $table->string('tarikh_mula')->nullable();
            $table->string('tarikh_bubar')->nullable();
            $table->text('nota')->nullable();
            $table->string('negeri')->nullable();
            $table->string('kawasan')->nullable();
            $table->unsignedInteger('member_count')->default(0);
            $table->json('members')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'usrah_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spi_naqib_usrah');
    }
};
