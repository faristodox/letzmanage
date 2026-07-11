<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spi_members', function (Blueprint $table) {
            // no_ahli is only unique within an organization, not globally.
            $table->dropUnique('spi_members_no_ahli_unique');
            $table->unique(['organization_id', 'no_ahli']);
        });
    }

    public function down(): void
    {
        Schema::table('spi_members', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'no_ahli']);
            $table->unique('no_ahli');
        });
    }
};
