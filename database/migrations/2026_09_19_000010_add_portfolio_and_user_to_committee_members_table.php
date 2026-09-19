<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('committee_members', function (Blueprint $table) {
            $table->foreignId('portfolio_id')->nullable()->after('organization_id')
                ->constrained('portfolios')->nullOnDelete();
            // Nullable — most roster entries are attendees who never log in;
            // this is only set for the subset who also get a User account.
            $table->foreignId('user_id')->nullable()->after('portfolio_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('committee_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('portfolio_id');
        });
    }
};
