<?php

namespace Database\Seeders;

use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Enums\Role;
use App\Models\WorkflowStep;
use App\Models\WorkflowTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds all eight workflow templates as configuration data (invariant #1).
 *
 * Short chains (4): Registration, Renewal, ActivityCalendar, AfterActivityReport
 *   — each is a single SDAO step requiring both members to approve.
 *
 * Proposal variants (4): RegularOnCalendar, RegularOffCalendar, ShsOnCalendar,
 *   ShsOffCalendar — each variant is its own template row, not a code branch.
 *   SDAO appears exactly once in every variant; off-calendar moves it to step 1.
 *   SHS replaces ProgramChair + Dean with a single Principal (invariant #8).
 */
class WorkflowTemplateSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->shortChain(FormType::OrganizationRegistration, 'Organization Registration');
        $this->shortChain(FormType::OrganizationRenewal, 'Organization Renewal');
        $this->shortChain(FormType::ActivityCalendar, 'Activity Calendar');
        $this->shortChain(FormType::AfterActivityReport, 'After-Activity Report');

        $this->template(
            FormType::ActivityProposal,
            ProposalVariant::RegularOnCalendar,
            'Activity Proposal — Regular School, On-Calendar',
            [
                [Role::Adviser, 1],
                [Role::ProgramChair, 1],
                [Role::Dean, 1],
                [Role::SdaoMember, 2],  // both members required
                [Role::AssistantDirectorAcademicServices, 1],
                [Role::AcademicDirector, 1],
                [Role::ExecutiveDirector, 1],
            ],
        );

        $this->template(
            FormType::ActivityProposal,
            ProposalVariant::RegularOffCalendar,
            'Activity Proposal — Regular School, Off-Calendar',
            [
                [Role::SdaoMember, 2],  // SDAO relocated to front for off-calendar
                [Role::Adviser, 1],
                [Role::ProgramChair, 1],
                [Role::Dean, 1],
                [Role::AssistantDirectorAcademicServices, 1],
                [Role::AcademicDirector, 1],
                [Role::ExecutiveDirector, 1],
            ],
        );

        $this->template(
            FormType::ActivityProposal,
            ProposalVariant::ShsOnCalendar,
            'Activity Proposal — Senior High School, On-Calendar',
            [
                [Role::Adviser, 1],
                [Role::Principal, 1],   // replaces ProgramChair + Dean
                [Role::SdaoMember, 2],
                [Role::AssistantDirectorAcademicServices, 1],
                [Role::AcademicDirector, 1],
                [Role::ExecutiveDirector, 1],
            ],
        );

        $this->template(
            FormType::ActivityProposal,
            ProposalVariant::ShsOffCalendar,
            'Activity Proposal — Senior High School, Off-Calendar',
            [
                [Role::SdaoMember, 2],  // SDAO relocated to front for off-calendar
                [Role::Adviser, 1],
                [Role::Principal, 1],
                [Role::AssistantDirectorAcademicServices, 1],
                [Role::AcademicDirector, 1],
                [Role::ExecutiveDirector, 1],
            ],
        );
    }

    /**
     * A short chain is a single SDAO step (both members required).
     */
    private function shortChain(FormType $formType, string $name): void
    {
        $this->template($formType, null, $name, [
            [Role::SdaoMember, 2],
        ]);
    }

    /**
     * @param  array<int, array{0: Role, 1: int}>  $steps
     */
    private function template(
        FormType $formType,
        ?ProposalVariant $variant,
        string $name,
        array $steps,
    ): void {
        $template = WorkflowTemplate::create([
            'form_type' => $formType,
            'variant' => $variant,
            'name' => $name,
        ]);

        foreach ($steps as $position => [$role, $requiredApprovals]) {
            WorkflowStep::create([
                'workflow_template_id' => $template->id,
                'position' => $position + 1,
                'role' => $role,
                'required_approvals' => $requiredApprovals,
            ]);
        }
    }
}
