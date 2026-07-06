<?php

namespace App\Approval;

use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Models\WorkflowTemplate;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Resolves a (FormType, ProposalVariant?) pair to the matching seeded template.
 *
 * For proposals, the variant is derived from the org's school structure
 * (belongsToSeniorHighSchool()) and the on/off-calendar flag — this is
 * legitimate routing input, not a hardcoded sequence (invariant #1).
 */
class WorkflowTemplateResolver
{
    /**
     * @throws ModelNotFoundException
     */
    public function resolve(FormType $formType, ?ProposalVariant $variant = null): WorkflowTemplate
    {
        return WorkflowTemplate::query()
            ->where('form_type', $formType)
            ->where('variant', $variant)
            ->with('steps')
            ->firstOrFail();
    }
}
