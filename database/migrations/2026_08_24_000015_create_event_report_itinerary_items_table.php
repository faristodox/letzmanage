<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_report_itinerary_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('time')->nullable();
            $table->string('activity')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_report_itinerary_items');
    }
};
