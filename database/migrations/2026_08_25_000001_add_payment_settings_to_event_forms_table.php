<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_forms', function (Blueprint $table) {
            $table->boolean('payment_enabled')->default(false)->after('checkin_onsite_registration_enabled');
            $table->json('payment_methods')->nullable()->after('payment_enabled');
            $table->decimal('payment_amount', 10, 2)->nullable()->after('payment_methods');
            $table->foreignId('pricing_field_id')->nullable()->after('payment_amount')
                ->constrained('event_form_fields')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_forms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pricing_field_id');
            $table->dropColumn(['payment_enabled', 'payment_methods', 'payment_amount']);
        });
    }
};
