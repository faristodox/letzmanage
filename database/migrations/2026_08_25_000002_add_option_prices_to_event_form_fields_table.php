<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_form_fields', function (Blueprint $table) {
            $table->json('option_prices')->nullable()->after('options');
        });
    }

    public function down(): void
    {
        Schema::table('event_form_fields', function (Blueprint $table) {
            $table->dropColumn('option_prices');
        });
    }
};
