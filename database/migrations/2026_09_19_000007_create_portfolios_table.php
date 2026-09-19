<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A committee/wing grouping (e.g. "Jawatankuasa WANITA") — lets a committee
 * member's account be scoped to only their own portfolio's Events and AI
 * MoM meetings. Global reference-style tenancy: organization_id has no DB
 * FK, matching committee_members' established convention for this app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolios');
    }
};
