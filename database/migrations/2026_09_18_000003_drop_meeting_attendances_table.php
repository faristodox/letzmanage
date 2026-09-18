<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Replaced by event_attendances (see 2026_09_18_000004) — attendance now
 * belongs to the Event, not the Meeting. Never deployed to production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('meeting_attendances');
    }

    public function down(): void
    {
        // Superseded by event_attendances — not worth reconstructing the old shape.
    }
};
