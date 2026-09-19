<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cutisekolah.com.my's public-holiday table lists a "Negeri" column per row
 * (e.g. a Sultan's birthday only applies to their own state) — previously
 * scraped into `description` but never parsed, so every organization saw
 * every state's holidays regardless of its chosen state. This stores the
 * parsed result: null means "applies nationwide", a JSON array of
 * MalaysianState slugs means "only these states". Only ever set for
 * source=cutisekolah/type=public rows — school holidays already use the
 * single `state` column, and Google-sourced rows stay nationwide-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->json('applicable_states')->nullable()->after('state');
        });
    }

    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropColumn('applicable_states');
        });
    }
};
