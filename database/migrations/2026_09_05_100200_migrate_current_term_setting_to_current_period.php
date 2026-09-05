<?php

use App\Enums\Term;
use App\Support\AcademicPeriod;
use App\Support\AcademicYear;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The old `current_term` setting (App\Support\CurrentTerm, now deleted)
     * carried no academic-year component. Its replacement, `current_period`
     * (App\Support\CurrentPeriod), needs one, so a legacy row's term is
     * combined with the wall-clock academic year — the same year
     * `AcademicYear::current()` would have reported at the moment this
     * migration runs, since that's what every reader of the old setting was
     * implicitly assuming anyway.
     *
     * A NO-OP when no legacy row exists (a fresh database, or the test
     * schema) — deliberately does NOT insert a default `current_period` row
     * in that case. CurrentPeriod::get() already falls back to a
     * clock-derived period when the row is absent, and inserting one here
     * unconditionally would plant a settings row into the baseline schema
     * every RefreshDatabase test starts from, defeating tests that assert on
     * "no setting row exists yet".
     */
    public function up(): void
    {
        $legacyTerm = DB::table('settings')->where('key', 'current_term')->value('value');

        if ($legacyTerm === null) {
            return;
        }

        $term = Term::tryFrom((string) $legacyTerm) ?? Term::FirstTerm;
        $period = new AcademicPeriod(AcademicYear::forDate(Date::now()), $term);

        DB::table('settings')->updateOrInsert(
            ['key' => 'current_period'],
            ['value' => $period->toString(), 'updated_at' => Date::now()],
        );

        DB::table('settings')->where('key', 'current_term')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $period = DB::table('settings')->where('key', 'current_period')->value('value');
        $parsed = AcademicPeriod::tryFromString($period);

        if ($parsed !== null) {
            DB::table('settings')->updateOrInsert(
                ['key' => 'current_term'],
                ['value' => $parsed->term->value, 'updated_at' => Date::now()],
            );
        }

        DB::table('settings')->where('key', 'current_period')->delete();
    }
};
