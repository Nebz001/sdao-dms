<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field-level revision diffs — the before/after values of whichever fields
 * belong to the sections the approver flagged, captured at the moment of
 * resubmission and frozen as display-ready strings (App\Approval\FieldChangeSet).
 *
 * Written ONLY on Resubmitted transitions; null on every other action.
 * Additive and nullable, exactly like flagged_sections and section_comments
 * before it — document_transitions stays append-only (invariant #7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_transitions', function (Blueprint $table) {
            $table->json('field_changes')->nullable()->after('section_comments');
        });
    }

    public function down(): void
    {
        Schema::table('document_transitions', function (Blueprint $table) {
            $table->dropColumn('field_changes');
        });
    }
};
