<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that belong to a single organization (tenant).
     */
    private array $tables = [
        'users',
        'branches',
        'office_spaces',
        'office_space_types',
        'bookings',
        'system_settings',
        'spi_members',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                // Nullable for now so the backfill migration can populate it;
                // no DB-level FK constraint (SQLite can't add one via ALTER, and
                // tenancy integrity is enforced in the application layer).
                $t->unsignedBigInteger('organization_id')->nullable()->after('id')->index();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('organization_id');
            });
        }
    }
};
