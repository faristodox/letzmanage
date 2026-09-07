<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_report_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->foreignId('event_id')->unique()->constrained('events')->cascadeOnDelete();
            $table->date('event_date')->nullable();
            $table->string('event_time')->nullable();
            $table->string('theme')->nullable();
            $table->string('venue')->nullable();
            $table->text('objectives')->nullable();
            $table->text('problems')->nullable();
            $table->text('achievements')->nullable();
            $table->text('directors_remarks')->nullable();
            $table->string('prepared_by_name')->nullable();
            $table->string('prepared_by_position')->nullable();
            $table->date('prepared_by_date')->nullable();
            $table->string('reviewed_by_name')->nullable();
            $table->string('reviewed_by_position')->nullable();
            $table->date('reviewed_by_date')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->string('approved_by_position')->nullable();
            $table->date('approved_by_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_report_details');
    }
};
