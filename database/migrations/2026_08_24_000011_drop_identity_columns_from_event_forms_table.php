<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identity (title/slug/description/banner) now lives solely on Event —
     * every event_forms row has been backfilled onto one by the previous
     * migration, so these columns are redundant.
     */
    public function up(): void
    {
        Schema::table('event_forms', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'slug']);
            $table->dropColumn(['title', 'slug', 'description', 'banner_path']);
        });
    }

    public function down(): void
    {
        Schema::table('event_forms', function (Blueprint $table) {
            $table->string('title')->nullable()->after('type');
            $table->string('slug')->nullable()->after('title');
            $table->text('description')->nullable()->after('slug');
            $table->string('banner_path')->nullable()->after('description');
        });
    }
};
