<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            // Nullable on purpose: a linked registrant's payment stays a real
            // transaction (accounting record) even if that response is later
            // deleted — it just becomes unlinked rather than vanishing.
            $table->foreignId('event_form_response_id')->nullable()->constrained('event_form_responses')->nullOnDelete();
            $table->string('type');
            $table->string('category');
            $table->decimal('amount', 10, 2);
            $table->date('transaction_date');
            $table->text('description')->nullable();
            $table->string('source')->default('manual');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_transactions');
    }
};
