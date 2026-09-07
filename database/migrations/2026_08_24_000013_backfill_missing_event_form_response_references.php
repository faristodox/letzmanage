<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Responses created before the reference column existed (or by any path
     * that bypasses EventFormResponse's `created` hook, e.g. this migration
     * itself) never got one backfilled — they're stuck on NULL forever
     * otherwise, which renders as a blank option wherever a response is
     * picked by reference (e.g. the finance ledger's "Link to Registrant").
     */
    public function up(): void
    {
        DB::table('event_form_responses')
            ->whereNull('reference')
            ->orderBy('id')
            ->each(function ($response) {
                DB::table('event_form_responses')
                    ->where('id', $response->id)
                    ->update(['reference' => sprintf('REG-%06d', $response->id)]);
            });
    }

    public function down(): void
    {
        // Not reversible — we don't know which references were auto-backfilled
        // here versus genuinely generated at creation time.
    }
};
