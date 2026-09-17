<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('committee_members', function (Blueprint $table) {
            $table->string('ic_number')->nullable()->after('position');
            $table->unique(['organization_id', 'ic_number']);
        });
    }

    public function down(): void
    {
        Schema::table('committee_members', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'ic_number']);
            $table->dropColumn('ic_number');
        });
    }
};
