<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Every event_forms row predates the Event entity and effectively *was*
     * the event. Give each one a matching events row (copying its identity)
     * and point it back via event_id, so nothing existing loses its data.
     */
    public function up(): void
    {
        $forms = DB::table('event_forms')->whereNull('event_id')->get();

        foreach ($forms as $form) {
            $eventId = DB::table('events')->insertGetId([
                'organization_id' => $form->organization_id,
                'title' => $form->title,
                'slug' => $form->slug,
                'description' => $form->description,
                'banner_path' => $form->banner_path,
                'status' => $form->status,
                'created_by' => $form->created_by,
                'created_at' => $form->created_at,
                'updated_at' => $form->updated_at,
            ]);

            DB::table('event_forms')->where('id', $form->id)->update([
                'event_id' => $eventId,
                'type' => 'registration',
            ]);
        }
    }

    public function down(): void
    {
        // Irreversible: the events rows this created are left in place, but
        // event_forms.event_id is nulled back out so a subsequent down() of
        // the schema migrations can proceed cleanly.
        DB::table('event_forms')->update(['event_id' => null]);
    }
};
