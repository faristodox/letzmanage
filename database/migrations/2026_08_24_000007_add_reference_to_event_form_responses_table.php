<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_form_responses', function (Blueprint $table) {
            $table->string('reference')->nullable()->index()->after('event_form_id');
        });
    }

    public function down(): void
    {
        Schema::table('event_form_responses', function (Blueprint $table) {
            $table->dropColumn('reference');
        });
    }
};
