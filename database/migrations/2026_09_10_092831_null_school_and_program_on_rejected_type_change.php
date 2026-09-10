<?php

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Refines 2026_09_09_100000_null_school_and_program_on_extra_curricular_organizations:
     * that migration picked each organization's most-recent registration/renewal
     * detail row "of any status" to decide current organization_type. That is
     * too broad — a Rejected renewal never took effect, so it must not be able
     * to outrank an earlier Approved record in deciding the org's type. Red
     * Cross Youth is the live example: its most-recent row is a Rejected
     * renewal claiming co_curricular, on top of an Approved registration that
     * says extra_curricular. The prior migration left it with school_id set
     * (SAHS, no program), which is unroutable — ProposalVariantResolver has no
     * "school but no program" case, so any activity proposal jams resolving
     * the program-chair step.
     *
     * This migration re-derives using the same "most-recent wins" rule but
     * excludes Rejected rows from that determination. Draft/InReview/Returned
     * rows still correctly outrank older Approved ones (a submission in
     * flight reflects the org's current claimed type), matching the original
     * migration's "any status" reasoning for those three statuses — only
     * Rejected is now excluded, since Rejected is the one status that
     * definitionally never took effect (DocumentStatus::isTerminal(), and
     * explicitly excluded from isInFlight()).
     */
    public function up(): void
    {
        $detailRows = DB::table('organization_registration_details as ord')
            ->join('documents as d', 'd.id', '=', 'ord.document_id')
            ->whereIn('d.form_type', [
                FormType::OrganizationRegistration->value,
                FormType::OrganizationRenewal->value,
            ])
            ->where('d.status', '!=', DocumentStatus::Rejected->value)
            ->orderBy('d.organization_id')
            ->orderByDesc('d.id')
            ->get(['d.organization_id', 'd.id', 'ord.organization_type']);

        $orgIdsToNull = $detailRows
            ->unique('organization_id')
            ->where('organization_type', 'extra_curricular')
            ->pluck('organization_id');

        DB::table('organizations')
            ->whereIn('id', $orgIdsToNull)
            ->where(fn ($q) => $q->whereNotNull('school_id')->orWhereNotNull('program_id'))
            ->update(['school_id' => null, 'program_id' => null]);
    }

    /**
     * Deliberately a no-op — same honesty as the migration this refines: the
     * original school_id/program_id values are not recoverable.
     */
    public function down(): void
    {
        //
    }
};
