<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_check_ins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->foreignId('event_form_id')->constrained('event_forms')->cascadeOnDelete();
            $table->foreignId('event_form_response_id')->unique()->constrained('event_form_responses')->cascadeOnDelete();
            $table->string('method');
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('checked_in_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_check_ins');
    }
};
