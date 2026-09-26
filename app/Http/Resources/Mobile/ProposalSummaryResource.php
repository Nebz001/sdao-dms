<?php

namespace App\Http\Resources\Mobile;

use App\ActivityProposals\ProposalReference;
use App\Models\Document;
use App\Models\User;
use App\Support\ApiTimestamp;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A queue row. Expects organization, activityProposal, submitter,
 * workflowTemplate.steps, transitions and stepApprovals already
 * eager-loaded on $resource (see DocumentController::queue()).
 *
 * @property-read Document $resource
 */
class ProposalSummaryResource extends JsonResource
{
    public function __construct(Document $resource, private readonly User $viewer)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $document = $this->resource;
        $progress = new ProposalProgress($document);

        return [
            'id' => ProposalReference::format($document),
            'title' => $document->activityProposal?->title ?? $document->title,
            'type' => 'activity_proposal',
            'status' => $progress->status,
            'submitted_by' => $document->submitter?->name ?? 'Unknown',
            'submitted_at' => ApiTimestamp::format(ProposalTimestamps::submittedAt($document)),
            'updated_at' => ApiTimestamp::format(ProposalTimestamps::updatedAt($document)),
            'current_stage' => $progress->currentStage,
            'current_stage_label' => $progress->currentStageLabel,
            'current_step' => $progress->currentStep,
            'total_steps' => $progress->totalSteps,
            'organization_name' => $document->organization->name,
            // Never re-checks the venue conflict per document — see
            // ProposalPermissions' own docblock.
            'permissions' => ProposalPermissions::for($document, $this->viewer, includeVenueCheck: false),
        ];
    }
}
