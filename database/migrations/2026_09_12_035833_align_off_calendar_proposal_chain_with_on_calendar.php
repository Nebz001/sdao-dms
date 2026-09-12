<?php

use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrects already-seeded workflow_steps data for the 3 off-calendar
 * activity-proposal templates so SDAO sits in the SAME position as its
 * on-calendar sibling, instead of being relocated to step 1. This is a data
 * fix, not a schema change — WorkflowTemplateSeeder.php was updated in the
 * same change to match, but running the seeder again is not something every
 * environment can be relied on to do, so the existing rows are rewritten
 * directly here.
 *
 * Mirrors WorkflowTemplateSeeder::template()'s own upsert-by-position
 * technique rather than hand-computing which individual positions changed —
 * simpler and less error-prone than a partial diff.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->applyShape(ProposalVariant::RegularOffCalendar, [
            [Role::Adviser, 1],
            [Role::ProgramChair, 1],
            [Role::Dean, 1],
            [Role::SdaoMember, 2],
            [Role::AssistantDirectorAcademicServices, 1],
            [Role::AcademicDirector, 1],
            [Role::ExecutiveDirector, 1],
        ]);

        $this->applyShape(ProposalVariant::ShsOffCalendar, [
            [Role::Adviser, 1],
            [Role::Principal, 1],
            [Role::SdaoMember, 2],
            [Role::AssistantDirectorAcademicServices, 1],
            [Role::AcademicDirector, 1],
            [Role::ExecutiveDirector, 1],
        ]);

        $this->applyShape(ProposalVariant::ExtraCurricularOffCalendar, [
            [Role::Adviser, 1],
            [Role::SdaoMember, 2],
            [Role::AssistantDirectorAcademicServices, 1],
            [Role::AcademicDirector, 1],
            [Role::ExecutiveDirector, 1],
        ]);
    }

    public function down(): void
    {
        $this->applyShape(ProposalVariant::RegularOffCalendar, [
            [Role::SdaoMember, 2],
            [Role::Adviser, 1],
            [Role::ProgramChair, 1],
            [Role::Dean, 1],
            [Role::AssistantDirectorAcademicServices, 1],
            [Role::AcademicDirector, 1],
            [Role::ExecutiveDirector, 1],
        ]);

        $this->applyShape(ProposalVariant::ShsOffCalendar, [
            [Role::SdaoMember, 2],
            [Role::Adviser, 1],
            [Role::Principal, 1],
            [Role::AssistantDirectorAcademicServices, 1],
            [Role::AcademicDirector, 1],
            [Role::ExecutiveDirector, 1],
        ]);

        $this->applyShape(ProposalVariant::ExtraCurricularOffCalendar, [
            [Role::SdaoMember, 2],
            [Role::Adviser, 1],
            [Role::AssistantDirectorAcademicServices, 1],
            [Role::AcademicDirector, 1],
            [Role::ExecutiveDirector, 1],
        ]);
    }

    /**
     * @param  array<int, array{0: Role, 1: int}>  $steps
     */
    private function applyShape(ProposalVariant $variant, array $steps): void
    {
        $templateId = DB::table('workflow_templates')
            ->where('form_type', FormType::ActivityProposal->value)
            ->where('variant', $variant->value)
            ->value('id');

        if ($templateId === null) {
            // Nothing seeded yet in this environment — WorkflowTemplateSeeder
            // will create the (already-corrected) rows itself.
            return;
        }

        foreach ($steps as $index => [$role, $requiredApprovals]) {
            DB::table('workflow_steps')->updateOrInsert(
                ['workflow_template_id' => $templateId, 'position' => $index + 1],
                ['role' => $role->value, 'required_approvals' => $requiredApprovals],
            );
        }
    }
};
