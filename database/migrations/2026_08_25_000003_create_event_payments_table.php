<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->foreignId('event_form_response_id')->unique()->constrained('event_form_responses')->cascadeOnDelete();
            $table->string('method');
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('MYR');
            $table->string('status')->default('pending');
            $table->string('chip_purchase_id')->nullable()->index();
            $table->string('receipt_path')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('event_transaction_id')->nullable()->constrained('event_transactions')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_payments');
    }
};
