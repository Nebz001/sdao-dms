<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IdentitySeeder's four School::create(...) calls were unconditional, while
 * RealRosterSeeder's were firstOrCreate(...) — running both against the same
 * database (the documented "WorkflowTemplateSeeder -> db:seed IdentitySeeder
 * -> demo:reset" restore sequence) left a second "Senior High School" row
 * behind, which the registration form's College dropdown then rendered
 * twice. IdentitySeeder itself is fixed to firstOrCreate in this same change
 * (see IdentitySeeder.php) so this can't recur; this migration is the
 * one-time cleanup for a database that already has the duplicate, plus a
 * unique index so no other code path can reintroduce it.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Keep the lowest id per name (same "lowest id wins" rule
        // ResetDemoData::wipe() already uses for duplicate workflow_templates)
        // and repoint every dependent row before deleting the rest — schools,
        // programs, and role_assignments all cascadeOnDelete on school_id, so
        // deleting a duplicate first would silently take its dependents with it.
        $duplicateGroups = DB::table('schools')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('count(*) > 1')
            ->pluck('name');

        foreach ($duplicateGroups as $name) {
            $ids = DB::table('schools')->where('name', $name)->orderBy('id')->pluck('id');
            $keepId = $ids->first();
            $duplicateIds = $ids->slice(1)->values();

            DB::table('organizations')->whereIn('school_id', $duplicateIds)->update(['school_id' => $keepId]);
            DB::table('programs')->whereIn('school_id', $duplicateIds)->update(['school_id' => $keepId]);
            DB::table('role_assignments')->whereIn('school_id', $duplicateIds)->update(['school_id' => $keepId]);

            DB::table('schools')->whereIn('id', $duplicateIds)->delete();
        }

        Schema::table('schools', function ($table) {
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function ($table) {
            $table->dropUnique(['name']);
        });

        // Merged duplicates are not recreated — this migration's up() is a
        // one-time data cleanup, not a reversible structural change.
    }
};
