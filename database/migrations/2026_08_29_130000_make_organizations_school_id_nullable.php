<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An Extra-Curricular organization is university-wide and has no college
 * (Phase 2 remediation item 3) — previously `school_id` was required even
 * for these, blocking a legitimate registration. `cascadeOnDelete()` is
 * unaffected: an org with a null school_id has no FK row to cascade from in
 * the first place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable(false)->change();
        });
    }
};
