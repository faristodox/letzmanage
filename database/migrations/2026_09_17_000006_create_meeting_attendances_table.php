<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('committee_member_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_in_at');
            $table->timestamps();

            $table->unique(['meeting_id', 'committee_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_attendances');
    }
};
