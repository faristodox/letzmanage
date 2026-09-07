<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_form_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->foreignId('event_form_id')->constrained('event_forms')->cascadeOnDelete();
            $table->string('label');
            $table->string('type');
            $table->json('options')->nullable();
            $table->boolean('required')->default(false);
            $table->string('help_text')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['event_form_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_form_fields');
    }
};
