<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Section-flagging redesign — additive per-section comments alongside
     * the existing shared `comment` and `flagged_sections`. Nullable and
     * purely additive: existing rows get null here forever (no backfill),
     * and every read path treats null as "render exactly as before this
     * column existed" — see App\Approval\SectionFlags and PLAN.md / the
     * section-comments design notes for the full rationale. Deliberately
     * NOT populated for Activity Calendar returns (deferred — its
     * "activity_{index}" keys aren't stable across a resubmit, so a
     * per-section comment tied to one wouldn't reliably track the same
     * activity after the student resubmits).
     */
    public function up(): void
    {
        Schema::table('document_transitions', function (Blueprint $table) {
            $table->json('section_comments')->nullable()->after('flagged_sections');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_transitions', function (Blueprint $table) {
            $table->dropColumn('section_comments');
        });
    }
};
