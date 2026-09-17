<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * meeting_attendances now represents two kinds of attendee: a committee
 * roster member (committee_member_id set) or a walk-in guest recorded via
 * "Allow new registration" (committee_member_id null, guest_* filled in) —
 * so it graduates from a plain belongsToMany pivot to a real model
 * (App\Models\MeetingAttendance).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_attendances', function (Blueprint $table) {
            // The composite unique below was the only index covering
            // meeting_id's own foreign key (meeting_id is its leading
            // column) — give meeting_id a dedicated index first so dropping
            // the composite doesn't leave that FK without one.
            $table->index('meeting_id');
            $table->dropUnique(['meeting_id', 'committee_member_id']);
            $table->foreignId('committee_member_id')->nullable()->change();
            $table->string('guest_name')->nullable()->after('committee_member_id');
            $table->string('guest_position')->nullable()->after('guest_name');
            $table->string('guest_ic_number')->nullable()->after('guest_position');

            // Unique index tolerates multiple NULL committee_member_id rows
            // (every RDBMS treats NULLs as distinct in a unique index) — so
            // guest rows are never deduplicated against each other, only
            // real roster members are prevented from double-checking-in.
            $table->unique(['meeting_id', 'committee_member_id']);
        });
    }

    public function down(): void
    {
        Schema::table('meeting_attendances', function (Blueprint $table) {
            $table->dropUnique(['meeting_id', 'committee_member_id']);
            $table->dropColumn(['guest_name', 'guest_position', 'guest_ic_number']);
            $table->foreignId('committee_member_id')->nullable(false)->change();
            $table->unique(['meeting_id', 'committee_member_id']);
            $table->dropIndex(['meeting_id']);
        });
    }
};
