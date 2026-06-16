<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('office_spaces', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('office_spaces')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('office_spaces', function (Blueprint $table) {
            $table->dropForeignIfExists('office_spaces_parent_id_foreign');
            $table->dropColumn('parent_id');
        });
    }
};
