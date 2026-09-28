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
