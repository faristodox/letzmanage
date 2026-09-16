<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('archived_file_id')->nullable()->constrained('archived_files')->nullOnDelete();
            $table->string('title');
            $table->string('status')->default('pending');
            $table->string('audio_gcs_object')->nullable();
            $table->string('gcs_operation_name')->nullable();
            $table->longText('transcript')->nullable();
            $table->longText('minutes')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
