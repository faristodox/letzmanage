<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Malaysia public holidays, synced from Google's public "Holidays in
 * Malaysia" calendar (see App\Console\Commands\SyncMalaysiaHolidays) —
 * deliberately not organization-scoped: the same holidays apply to every
 * org on this platform, so there's no reason to store them per-tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['date', 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
