<?php

namespace App\Console\Commands;

use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Enums\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Validates the workflow_templates/workflow_steps configuration data
 * (invariant #1) against the shape WorkflowTemplateSeeder is supposed to
 * produce: exactly 10 rows — the 4 null-variant short chains plus the 6
 * ActivityProposal variants — each with exactly one row and its exact
 * ordered step list.
 *
 * The expected shape is intentionally duplicated here rather than derived
 * from WorkflowTemplateSeeder itself: this command exists to catch the case
 * where the seeded data has drifted from what the seeder would produce (a
 * bad manual fix, a partial reseed, a duplicate left behind by a bug like
 * the one ResetDemoData::wipe() works around), so it must not trust the same
 * source it's meant to be checking.
 *
 * Duplicates are the primary failure mode this command exists to catch, not
 * an afterthought: workflow_templates has a unique(['form_type', 'variant'])
 * index, but Postgres treats NULL as distinct in unique indexes, so the 4
 * short-chain rows (variant IS NULL) are NOT actually protected from
 * duplication by that constraint — see ResetDemoData.php's own dedupe pass,
 * which exists for exactly this reason. WorkflowTemplateResolver::resolve()
 * uses firstOrFail() with no orderBy, so if a duplicate ever exists, which
 * row it picks is undefined — silently routing documents through a stale or
 * wrong chain.
 *
 * Read-only: this command only reports. Fixing a failure (reseeding, or
 * deleting a duplicate) is a separate, deliberate action.
 */
class CheckWorkflowTemplates extends Command
{
    protected $signature = 'check:workflow-templates';

    protected $description = 'Validate the workflow_templates configuration against the expected 10-row shape (missing, duplicate, and mismatched rows).';

    public function handle(): int
    {
        $failures = [];

        foreach ($this->expectedTemplates() as [$formType, $variant, $expectedSteps]) {
            $label = $this->label($formType, $variant);

            $matches = DB::table('workflow_templates')
                ->where('form_type', $formType->value)
                ->where('variant', $variant?->value)
                ->get(['id']);

            if ($matches->count() === 0) {
                $failures[] = "[MISSING] {$label} — no workflow_templates row found";

                continue;
            }

            if ($matches->count() > 1) {
                $ids = $matches->pluck('id')->implode(', ');
                $failures[] = "[DUPLICATE] {$label} — {$matches->count()} rows found (ids: {$ids}); expected exactly 1";

                continue;
            }

            $templateId = $matches->first()->id;
            $actualSteps = DB::table('workflow_steps')
                ->where('workflow_template_id', $templateId)
                ->orderBy('position')
                ->get(['position', 'role', 'required_approvals']);

            $mismatch = $this->compareSteps($expectedSteps, $actualSteps);

            if ($mismatch !== null) {
                $failures[] = "[MISMATCH] {$label} (template id {$templateId}) — {$mismatch}"
                    ."\n    expected: ".$this->formatExpected($expectedSteps)
                    ."\n    actual:   ".$this->formatActual($actualSteps);
            }
        }

        if ($failures === []) {
            $this->info('All 10 workflow templates present, unique, and correctly shaped.');

            return self::SUCCESS;
        }

        $this->error(count($failures).' workflow template check(s) failed:');
        foreach ($failures as $failure) {
            $this->line($failure);
        }

        return self::FAILURE;
    }

    /**
     * The expected (form_type, variant) => ordered step list shape, mirroring
     * WorkflowTemplateSeeder::run() exactly (see this class's docblock for
     * why it's duplicated rather than derived from the seeder).
     *
     * @return array<int, array{0: FormType, 1: ?ProposalVariant, 2: array<int, array{0: Role, 1: int}>}>
     */
    private function expectedTemplates(): array
    {
        $shortChain = [[Role::SdaoMember, 2]];

        return [
            [FormType::OrganizationRegistration, null, $shortChain],
            [FormType::OrganizationRenewal, null, $shortChain],
            [FormType::ActivityCalendar, null, $shortChain],
            [FormType::AfterActivityReport, null, $shortChain],

            [FormType::ActivityProposal, ProposalVariant::RegularOnCalendar, [
                [Role::Adviser, 1],
                [Role::ProgramChair, 1],
                [Role::Dean, 1],
                [Role::SdaoMember, 2],
                [Role::AssistantDirectorAcademicServices, 1],
                [Role::AcademicDirector, 1],
                [Role::ExecutiveDirector, 1],
            ]],
            [FormType::ActivityProposal, ProposalVariant::RegularOffCalendar, [
                [Role::Adviser, 1],
                [Role::ProgramChair, 1],
                [Role::Dean, 1],
                [Role::SdaoMember, 2],
                [Role::AssistantDirectorAcademicServices, 1],
                [Role::AcademicDirector, 1],
                [Role::ExecutiveDirector, 1],
            ]],
            [FormType::ActivityProposal, ProposalVariant::ShsOnCalendar, [
                [Role::Adviser, 1],
                [Role::Principal, 1],
                [Role::SdaoMember, 2],
                [Role::AssistantDirectorAcademicServices, 1],
                [Role::AcademicDirector, 1],
                [Role::ExecutiveDirector, 1],
            ]],
            [FormType::ActivityProposal, ProposalVariant::ShsOffCalendar, [
                [Role::Adviser, 1],
                [Role::Principal, 1],
                [Role::SdaoMember, 2],
                [Role::AssistantDirectorAcademicServices, 1],
                [Role::AcademicDirector, 1],
                [Role::ExecutiveDirector, 1],
            ]],
            [FormType::ActivityProposal, ProposalVariant::ExtraCurricularOnCalendar, [
                [Role::Adviser, 1],
                [Role::SdaoMember, 2],
                [Role::AssistantDirectorAcademicServices, 1],
                [Role::AcademicDirector, 1],
                [Role::ExecutiveDirector, 1],
            ]],
            [FormType::ActivityProposal, ProposalVariant::ExtraCurricularOffCalendar, [
                [Role::Adviser, 1],
                [Role::SdaoMember, 2],
                [Role::AssistantDirectorAcademicServices, 1],
                [Role::AcademicDirector, 1],
                [Role::ExecutiveDirector, 1],
            ]],
        ];
    }

    /**
     * @param  array<int, array{0: Role, 1: int}>  $expectedSteps
     * @param  Collection<int, stdClass>  $actualSteps
     */
    private function compareSteps(array $expectedSteps, Collection $actualSteps): ?string
    {
        if ($actualSteps->count() !== count($expectedSteps)) {
            return "expected {$this->pluralSteps(count($expectedSteps))}, found {$this->pluralSteps($actualSteps->count())}";
        }

        foreach ($expectedSteps as $index => [$expectedRole, $expectedApprovals]) {
            $actual = $actualSteps[$index];
            $expectedPosition = $index + 1;

            if ((int) $actual->position !== $expectedPosition) {
                return "step at index {$index} has position {$actual->position}, expected {$expectedPosition}";
            }

            if ($actual->role !== $expectedRole->value) {
                return "step {$expectedPosition} has role \"{$actual->role}\", expected \"{$expectedRole->value}\"";
            }

            if ((int) $actual->required_approvals !== $expectedApprovals) {
                return "step {$expectedPosition} ({$expectedRole->value}) requires {$actual->required_approvals} approval(s), expected {$expectedApprovals}";
            }
        }

        return null;
    }

    private function pluralSteps(int $count): string
    {
        return $count === 1 ? '1 step' : "{$count} steps";
    }

    /**
     * @param  array<int, array{0: Role, 1: int}>  $expectedSteps
     */
    private function formatExpected(array $expectedSteps): string
    {
        $parts = [];
        foreach ($expectedSteps as $index => [$role, $approvals]) {
            $parts[] = ($index + 1).':'.$role->value.'×'.$approvals;
        }

        return implode(', ', $parts);
    }

    /**
     * @param  Collection<int, stdClass>  $actualSteps
     */
    private function formatActual(Collection $actualSteps): string
    {
        return $actualSteps
            ->map(fn ($step) => "{$step->position}:{$step->role}×{$step->required_approvals}")
            ->implode(', ');
    }

    private function label(FormType $formType, ?ProposalVariant $variant): string
    {
        return $variant === null
            ? $formType->value
            : "{$formType->value} / {$variant->value}";
    }
}
