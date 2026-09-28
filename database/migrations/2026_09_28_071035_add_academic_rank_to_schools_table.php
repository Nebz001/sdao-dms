<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The single source of truth for which schools the Academic
     * (Co-Curricular) registration flow may offer, and in what order.
     * Kept here (rather than only in App\Models\School) so a fresh
     * production database gets the same backfill a seeded one does.
     *
     * @var array<string, int>
     */
    private const ACADEMIC_RANKS = [
        'School of Architecture, Computing, and Engineering' => 1, // SACE
        'School of Accountancy, Business, and Management' => 2, // SABM
        'School of Allied Health and Sciences' => 3, // SAHS
        'Senior High School' => 4, // SHS
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->unsignedTinyInteger('academic_rank')->nullable()->after('type');
        });

        foreach (self::ACADEMIC_RANKS as $name => $rank) {
            DB::table('schools')->where('name', $name)->update(['academic_rank' => $rank]);
        }

        // Matched-by-name, so a rename or typo in either place silently
        // leaves that row unranked rather than raising here — this count
        // check is what turns that into a loud failure instead. 0 is the
        // legitimate "schools table not seeded yet" case (a fresh install
        // migrates before RealRosterSeeder/IdentitySeeder ever run, and
        // those seeders set academic_rank themselves on the rows they
        // create) — only a PARTIAL match (1-3) means the table already has
        // some, but not all, of the 4 expected names, which is the
        // corrupt/renamed state this guards against.
        $rankedCount = DB::table('schools')->whereNotNull('academic_rank')->count();
        $expected = count(self::ACADEMIC_RANKS);

        if ($rankedCount !== 0 && $rankedCount !== $expected) {
            throw new RuntimeException(sprintf(
                'Expected to rank exactly %d schools by exact name (or 0, on a database not yet seeded), but ranked %d. '.
                'The schools table has a renamed, duplicated, or missing row among: %s.',
                $expected,
                $rankedCount,
                implode(', ', array_keys(self::ACADEMIC_RANKS)),
            ));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('academic_rank');
        });
    }
};
