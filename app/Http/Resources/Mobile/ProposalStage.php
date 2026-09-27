<?php

namespace App\Http\Resources\Mobile;

use App\Enums\Role;
use App\Enums\TransitionAction;
use App\Models\DocumentTransition;
use App\Models\WorkflowStep;
use Illuminate\Support\Collection;

/**
 * The mobile contract's `current_stage`/history `stage` values, derived
 * from a WorkflowStep's role — never a hardcoded per-chain list, so a role
 * added to any workflow template gets a stage automatically (a missing
 * match arm below is a build-time error, not a silent runtime gap).
 */
class ProposalStage
{
    /**
     * @return array{0: string, 1: string} [stage key, stage label]
     */
    public static function forRole(Role $role): array
    {
        return match ($role) {
            Role::Adviser => ['adviser_review', 'Adviser Review'],
            Role::ProgramChair => ['program_chair_review', 'Program Chair Review'],
            Role::Dean => ['dean_review', 'Dean Review'],
            Role::Principal => ['principal_review', 'Principal Review'],
            Role::SdaoMember => ['sdao_review', 'SDAO Review'],
            Role::AssistantDirectorAcademicServices => ['assistant_director_review', 'Asst. Director of Academic Services Review'],
            Role::AcademicDirector => ['academic_director_review', 'Academic Director Review'],
            Role::ExecutiveDirector => ['executive_director_review', 'Executive Director Review'],
            Role::Student => ['submitted', 'Submitted'],
        };
    }

    /**
     * The history row mapping (CLAUDE.md-adjacent rule, see the plan):
     * Submitted/Resubmitted always read as "submitted"; Approved/Returned
     * read as the step they happened AT; Advanced reads as the step it
     * moved TO, with the actor credited for the step it moved FROM (the
     * engine already stores the transition at the destination position);
     * Rejected/Completed are the two fixed terminal stages; Withdrawn
     * keeps its own step's stage but is always attributed to "System".
     *
     * @param  Collection<int, WorkflowStep>  $steps
     * @return array{0: string, 1: string, 2: string} [stage key, stage label, actor role label]
     */
    public static function forTransition(DocumentTransition $transition, Collection $steps): array
    {
        $stepAt = fn (?int $position) => $position === null ? null : $steps->firstWhere('position', $position);

        if (in_array($transition->action, [TransitionAction::Submitted, TransitionAction::Resubmitted], true)) {
            return ['submitted', 'Submitted', 'Organization Officer'];
        }

        if ($transition->action === TransitionAction::Advanced) {
            $nextStep = $stepAt($transition->step_position);
            $prevStep = $stepAt($transition->step_position - 1);
            [$stage, $stageLabel] = $nextStep !== null ? self::forRole($nextStep->role) : ['submitted', 'Submitted'];

            return [$stage, $stageLabel, $prevStep?->role->label() ?? 'Unknown'];
        }

        if ($transition->action === TransitionAction::Rejected) {
            $step = $stepAt($transition->step_position);

            return ['rejected', 'Rejected', $step?->role->label() ?? 'Unknown'];
        }

        if ($transition->action === TransitionAction::Completed) {
            $step = $stepAt($transition->step_position);

            return ['completed', 'Completed', $step?->role->label() ?? 'Unknown'];
        }

        if ($transition->action === TransitionAction::Withdrawn) {
            $step = $stepAt($transition->step_position);
            [$stage, $stageLabel] = $step !== null ? self::forRole($step->role) : ['submitted', 'Submitted'];

            return [$stage, $stageLabel, 'System'];
        }

        // Approved, Returned
        $step = $stepAt($transition->step_position);
        [$stage, $stageLabel] = $step !== null ? self::forRole($step->role) : ['submitted', 'Submitted'];

        return [$stage, $stageLabel, $step?->role->label() ?? 'Unknown'];
    }
}
