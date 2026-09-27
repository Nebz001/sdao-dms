<?php

namespace App\Http\Resources\Mobile;

use App\ActivityProposals\ProposalReference;
use App\Approval\SectionFlags;
use App\Attachments\AttachmentSlots;
use App\Enums\FormType;
use App\Models\ActivityProposal;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\User;
use App\Support\ApiTimestamp;
use App\Support\DisplayTimezone;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The full Activity Proposal detail. Expects organization, submitter,
 * activityProposal.calendarActivity, workflowTemplate.steps, transitions
 * (with actor), stepApprovals and attachments already eager-loaded on
 * $resource — see DocumentController::show().
 *
 * @property-read Document $resource
 */
class ProposalResource extends JsonResource
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
        $proposal = $document->activityProposal;
        $progress = new ProposalProgress($document);

        return [
            'id' => ProposalReference::format($document),
            'title' => $proposal?->title ?? $document->title,
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
            'description' => $proposal?->activity_description,
            'permissions' => ProposalPermissions::for($document, $this->viewer, includeVenueCheck: true),
            'attachments' => ProposalAttachmentResource::collection(self::orderedAttachments($document->attachments)),
            'activity_proposal' => $this->activityProposalBlock($proposal, $progress),
            'history' => ProposalHistoryResource::collectionFor(
                $document->transitions,
                $document->workflowTemplate?->steps ?? collect(),
            )->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activityProposalBlock(?ActivityProposal $proposal, ProposalProgress $progress): array
    {
        if ($proposal === null) {
            return [];
        }

        $activity = $proposal->calendarActivity;
        [$startsAt, $endsAt] = self::activityInstants($activity);
        $partnerOrgs = $proposal->partner_organizations ?? [];

        return [
            'venue' => $activity?->venue,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'nature_of_activity' => $proposal->activityNatureLabel,
            'type_of_activity' => $proposal->activityTypeLabel,
            'partner_organizations' => array_map(
                fn (array $p) => $p['name'],
                $partnerOrgs,
            ),
            'partner_organization_details' => array_map(
                fn (array $p) => ['organization_id' => $p['organization_id'], 'name' => $p['name']],
                $partnerOrgs,
            ),
            'target_sdgs' => $proposal->target_sdg?->map(fn ($s) => $s->label())->values()->all() ?? [],
            'proposed_budget' => $proposal->proposed_budget !== null ? (float) $proposal->proposed_budget : null,
            'budget_source' => $proposal->budget_source?->label(),
            'objectives' => self::splitObjectives($proposal->objectives),
            'activity_description' => $proposal->activity_description,
            'criteria_mechanics' => $proposal->criteria_mechanics,
            'program_flow' => array_map(
                fn ($line) => ['activity' => $line, 'duration' => ''],
                self::splitLines($proposal->program_flow),
            ),
            'expenses' => self::expenses($proposal->expense_items),
            'responsible_persons' => array_map(
                fn ($name) => ['name' => $name, 'role' => ''],
                $proposal->responsible_persons ?? [],
            ),
            'revision_sections' => array_map(
                fn ($flag) => $flag->label,
                SectionFlags::for(FormType::ActivityProposal),
            ),
            'approval' => [
                'stage_label' => $progress->approvalStageLabel,
                'approved_count' => $progress->approvedCount,
                'total_count' => $progress->requiredCount,
            ],
        ];
    }

    /**
     * Slot order (per AttachmentSlots), then id — never upload order.
     *
     * @param  Collection<int, DocumentAttachment>  $attachments
     * @return Collection<int, DocumentAttachment>
     */
    private static function orderedAttachments($attachments)
    {
        $slotOrder = collect(AttachmentSlots::for(FormType::ActivityProposal))->pluck('key')->values()->all();

        return $attachments->sort(function ($a, $b) use ($slotOrder) {
            $aIndex = array_search($a->slot_key, $slotOrder, true);
            $bIndex = array_search($b->slot_key, $slotOrder, true);
            $aIndex = $aIndex === false ? count($slotOrder) : $aIndex;
            $bIndex = $bIndex === false ? count($slotOrder) : $bIndex;

            return $aIndex <=> $bIndex ?: $a->id <=> $b->id;
        })->values();
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private static function activityInstants(?CalendarActivity $activity): array
    {
        if ($activity === null) {
            return [null, null];
        }

        $date = $activity->activity_date->toDateString();

        $starts = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$activity->start_time}", DisplayTimezone::ASIA_MANILA);
        $ends = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$activity->end_time}", DisplayTimezone::ASIA_MANILA);

        return [ApiTimestamp::format($starts), ApiTimestamp::format($ends)];
    }

    /**
     * @return array<int, string>
     */
    private static function splitLines(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];

        return collect($lines)
            ->map(fn (string $line) => trim($line))
            ->filter(fn (string $line) => $line !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function splitObjectives(?string $text): array
    {
        return array_map(
            fn ($line) => preg_replace('/^[-*•]\s*/', '', $line),
            self::splitLines($text),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $items
     * @return array<int, array<string, mixed>>
     */
    private static function expenses(?array $items): array
    {
        if (empty($items)) {
            return [];
        }

        return array_map(function ($row) {
            $quantity = is_numeric($row['quantity'] ?? null) ? +$row['quantity'] : 0;
            $unitPrice = is_numeric($row['unit_price'] ?? null) ? +$row['unit_price'] : 0;
            $total = round(((float) $quantity) * ((float) $unitPrice), 2);

            return [
                'material' => $row['material'] ?? '',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total' => $total,
            ];
        }, $items);
    }
}
