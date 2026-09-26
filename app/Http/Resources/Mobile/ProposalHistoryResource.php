<?php

namespace App\Http\Resources\Mobile;

use App\Models\DocumentTransition;
use App\Models\WorkflowStep;
use App\Support\ApiTimestamp;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @property-read DocumentTransition $resource
 */
class ProposalHistoryResource extends JsonResource
{
    private const array ACTION_LABELS = [
        'submitted' => 'Submitted',
        'approved' => 'Approved',
        'advanced' => 'Forwarded',
        'returned' => 'Revision Requested',
        'resubmitted' => 'Resubmitted',
        'rejected' => 'Rejected',
        'completed' => 'Completed',
        'withdrawn' => 'Withdrawn',
    ];

    /**
     * @param  Collection<int, WorkflowStep>  $steps
     */
    public function __construct(DocumentTransition $resource, private readonly Collection $steps)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        [$stage, $stageLabel, $actorRoleLabel] = ProposalStage::forTransition($this->resource, $this->steps);

        return [
            'id' => "transition-{$this->resource->id}",
            'action' => self::ACTION_LABELS[$this->resource->action->value] ?? $this->resource->action->value,
            'stage' => $stage,
            'stage_label' => $stageLabel,
            'actor_name' => $this->resource->actor?->name ?? 'System',
            'actor_role' => $actorRoleLabel,
            'timestamp' => ApiTimestamp::format($this->resource->created_at),
            'remarks' => $this->resource->comment,
        ];
    }

    /**
     * @param  Collection<int, DocumentTransition>  $transitions
     * @param  Collection<int, WorkflowStep>  $steps
     * @return Collection<int, self>
     */
    public static function collectionFor(Collection $transitions, Collection $steps): Collection
    {
        return $transitions->map(fn (DocumentTransition $t) => new self($t, $steps));
    }
}
