<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Confirmed attendees for a Committee Meeting-type Event's check-in — either
 * a roster member (committee_member_id set) or a walk-in guest recorded via
 * "Allow new registration" (committee_member_id null, guest_* set instead).
 * See App\Models\EventAttendance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('committee_member_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_position')->nullable();
            $table->string('guest_ic_number')->nullable();
            $table->timestamp('checked_in_at');
            $table->timestamps();

            // Tolerates multiple NULL committee_member_id rows (every RDBMS
            // treats NULLs as distinct in a unique index) — guest rows are
            // never deduplicated against each other, only real roster
            // members are prevented from double-checking-in.
            $table->unique(['event_id', 'committee_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_attendances');
    }
};
