<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tables = [
        'users',
        'branches',
        'office_spaces',
        'office_space_types',
        'bookings',
        'system_settings',
        'spi_members',
    ];

    private string $slug = 'ikram-setiawangsa';

    public function up(): void
    {
        // Only run on an existing install that already has data but no organizations.
        // Fresh installs (tests, new deployments) skip this entirely.
        $hasLegacyData = DB::table('users')->exists() || DB::table('branches')->exists();

        if (! $hasLegacyData || DB::table('organizations')->exists()) {
            return;
        }

        $now = now();

        $orgId = DB::table('organizations')->insertGetId([
            'name' => 'IKRAM Setiawangsa',
            'slug' => $this->slug,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($this->tables as $table) {
            DB::table($table)->whereNull('organization_id')->update(['organization_id' => $orgId]);
        }
    }

    public function down(): void
    {
        $orgId = DB::table('organizations')->where('slug', $this->slug)->value('id');

        if (! $orgId) {
            return;
        }

        foreach ($this->tables as $table) {
            DB::table($table)->where('organization_id', $orgId)->update(['organization_id' => null]);
        }

        DB::table('organizations')->where('id', $orgId)->delete();
    }
};
