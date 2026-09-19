<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Events scraped from the "Jawatankuasa WANITA" committee's own Google
 * Sheet calendar (a yearly grid the committee edits directly) — see
 * App\Services\WanitaCalendarSyncService. Org-scoped, unlike Holiday, since
 * this is one specific organization's own committee calendar, not shared
 * reference data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wanita_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('title');
            $table->timestamps();

            $table->unique(['organization_id', 'date', 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wanita_calendar_events');
    }
};
