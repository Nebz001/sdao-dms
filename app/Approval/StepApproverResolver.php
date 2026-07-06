<?php

namespace App\Approval;

use App\Enums\Role;
use App\Identity\RoleDirectory;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Collection;
use LogicException;

/**
 * Bridges a configured WorkflowStep to the person(s) who must act on it,
 * using Slice 0's RoleDirectory scoped by the document's organization.
 *
 * This is the only place role→person resolution happens in the engine.
 *
 * @return Collection<int, User>
 */
class StepApproverResolver
{
    public function __construct(private readonly RoleDirectory $directory) {}

    /**
     * Returns the resolved approver(s) for the given step on the given document.
     *
     * @return Collection<int, User>
     */
    public function approversFor(WorkflowStep $step, Document $document): Collection
    {
        $org = $document->organization;

        $user = match ($step->role) {
            Role::Adviser => $this->directory->adviserFor($org),
            Role::ProgramChair => $this->directory->programChairFor($org),
            Role::Dean => $this->directory->deanFor($org),
            Role::Principal => $this->directory->principalFor($org),
            Role::AssistantDirectorAcademicServices => $this->directory->assistantDirectorAcademicServices(),
            Role::AcademicDirector => $this->directory->academicDirector(),
            Role::ExecutiveDirector => $this->directory->executiveDirector(),
            Role::SdaoMember => null, // handled separately — returns multiple users
            Role::Student => throw new LogicException('Students are not approvers.'),
        };

        if ($step->role === Role::SdaoMember) {
            return $this->directory->sdaoMembers();
        }

        /** @var User $user */
        return new Collection([$user]);
    }
}
