<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_spaces', function (Blueprint $table) {
            $table->string('maintenance_note', 500)->nullable()->after('status');
            $table->date('maintenance_until')->nullable()->after('maintenance_note');
        });
    }

    public function down(): void
    {
        Schema::table('office_spaces', function (Blueprint $table) {
            $table->dropColumn(['maintenance_note', 'maintenance_until']);
        });
    }
};
