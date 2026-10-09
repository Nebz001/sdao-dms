<?php

namespace App\Dashboard;

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Enums\Role;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\WorkflowTemplate;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Stage-by-stage counts for activity proposals submitted this academic year,
 * one funnel per chain variant that actually has proposals. Step position is
 * not comparable across variants (CLAUDE.md invariant #8), so each variant is
 * its own funnel built from its own template's steps, never from raw position
 * numbers.
 *
 * A step's bar is the number of proposals that have cleared it. A proposal
 * cleared step N when it reached a later position, or is Approved. "Reached"
 * is the highest `step_position` over its submitted, advanced and resubmitted
 * transitions; `advanced` records the NEXT position, so an advance out of
 * step N shows as position N + 1. Rejected and returned proposals are counted
 * at the position they reached, so they simply stop short of later steps.
 */
class ProposalFunnelData
{
    /**
     * @return array<int, array{variant: string, label: string, submitted: int, steps: array<int, array{label: string, count: int}>, approved: int}>
     */
    public function forAcademicYear(CarbonInterface $yearStart, CarbonInterface $yearEnd): array
    {
        $proposals = Document::query()
            ->where('form_type', FormType::ActivityProposal->value)
            ->whereNotNull('variant')
            ->whereBetween('created_at', [$yearStart, $yearEnd])
            ->get(['id', 'variant', 'status']);

        if ($proposals->isEmpty()) {
            return [];
        }

        $reached = DocumentTransition::query()
            ->whereIn('document_id', $proposals->pluck('id'))
            ->whereIn('action', [
                TransitionAction::Submitted->value,
                TransitionAction::Advanced->value,
                TransitionAction::Resubmitted->value,
            ])
            ->selectRaw('document_id, max(step_position) as reached')
            ->groupBy('document_id')
            ->pluck('reached', 'document_id');

        $templates = WorkflowTemplate::query()
            ->with('steps')
            ->active()
            ->where('form_type', FormType::ActivityProposal->value)
            ->whereIn('variant', $proposals->pluck('variant')->map->value->unique())
            ->get()
            ->keyBy(fn (WorkflowTemplate $template) => $template->variant->value);

        return collect(ProposalVariant::cases())
            ->map(fn (ProposalVariant $variant) => $this->funnelFor(
                $variant,
                $proposals->filter(fn (Document $d) => $d->variant === $variant),
                $reached,
                $templates->get($variant->value),
            ))
            ->filter()
            ->sortByDesc('submitted')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Document>  $proposals
     * @param  Collection<int|string, mixed>  $reached
     * @return array{variant: string, label: string, submitted: int, steps: array<int, array{label: string, count: int}>, approved: int}|null
     */
    private function funnelFor(ProposalVariant $variant, Collection $proposals, Collection $reached, ?WorkflowTemplate $template): ?array
    {
        if ($proposals->isEmpty() || $template === null) {
            return null;
        }

        $approved = $proposals->where('status', DocumentStatus::Approved)->count();

        return [
            'variant' => $variant->value,
            'label' => $variant->label(),
            'submitted' => $proposals->count(),
            'steps' => $template->steps->map(fn ($step) => [
                'label' => $step->role === Role::SdaoMember ? 'SDAO' : $step->role->label(),
                'count' => $proposals->filter(
                    fn (Document $d) => $d->status === DocumentStatus::Approved
                        || (int) ($reached[$d->id] ?? 0) > $step->position
                )->count(),
            ])->values()->all(),
            'approved' => $approved,
        ];
    }
}
