<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('office_spaces', function (Blueprint $table) {
            $table->foreignId('type_id')->nullable()->after('type')->constrained('office_space_types');
        });

        $typeMap = [
            'meeting_room' => 'Meeting Room',
            'training_room' => 'Training Room',
            'hot_desk' => 'Hot Desk',
        ];

        foreach ($typeMap as $enumValue => $name) {
            $typeId = DB::table('office_space_types')->where('name', $name)->value('id');

            DB::table('office_spaces')->where('type', $enumValue)->update(['type_id' => $typeId]);
        }

        Schema::table('office_spaces', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('office_spaces', function (Blueprint $table) {
            $table->enum('type', ['meeting_room', 'training_room', 'hot_desk'])->nullable()->after('type_id');
        });

        $typeMap = [
            'Meeting Room' => 'meeting_room',
            'Training Room' => 'training_room',
            'Hot Desk' => 'hot_desk',
        ];

        foreach ($typeMap as $name => $enumValue) {
            $typeId = DB::table('office_space_types')->where('name', $name)->value('id');

            DB::table('office_spaces')->where('type_id', $typeId)->update(['type' => $enumValue]);
        }

        Schema::table('office_spaces', function (Blueprint $table) {
            $table->dropConstrainedForeignId('type_id');
        });
    }
};
