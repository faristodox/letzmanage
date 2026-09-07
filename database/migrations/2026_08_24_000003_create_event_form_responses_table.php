<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_form_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->foreignId('event_form_id')->constrained('event_forms')->cascadeOnDelete();
            $table->json('answers');
            $table->string('submitted_ip')->nullable();
            $table->timestamps();

            $table->index(['event_form_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_form_responses');
    }
};
