<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_forms', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('organization_id')->constrained('events')->cascadeOnDelete();
            $table->string('type')->default('registration')->after('event_id');
        });
    }

    public function down(): void
    {
        Schema::table('event_forms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
            $table->dropColumn('type');
        });
    }
};
