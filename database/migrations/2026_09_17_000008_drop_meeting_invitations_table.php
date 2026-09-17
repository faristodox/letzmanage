<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The pre-selected invite list ("Invitation only" mode) was replaced by a
 * simpler "Allow new registration" toggle (walk-ins register themselves with
 * name + position instead of admins pre-selecting from the roster) before
 * this ever shipped to production — never any real data to preserve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('meeting_invitations');
    }

    public function down(): void
    {
        Schema::create('meeting_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('committee_member_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['meeting_id', 'committee_member_id']);
        });
    }
};
