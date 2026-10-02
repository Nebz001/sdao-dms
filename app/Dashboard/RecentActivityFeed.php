<?php

namespace App\Dashboard;

use App\Enums\TransitionAction;
use App\Models\DocumentTransition;
use Illuminate\Support\Str;

/**
 * The dashboard's "Recent activity" card: the last few things people did to
 * documents, one row per action, newest first.
 *
 * Only the four outcomes the card has badges for are shown. An approval that
 * advances or completes a document writes TWO transitions (`approved`, then
 * `advanced` or `completed`) by the same actor at the same moment, so the
 * second one is left out as a duplicate of the first. A system `withdrawn`
 * has no actor to show. The Activity Log still lists every row.
 */
class RecentActivityFeed
{
    public const int LIMIT = 5;

    /**
     * The transition actions that do not appear on the dashboard feed.
     *
     * @var array<int, string>
     */
    private const array HIDDEN_ACTIONS = [
        TransitionAction::Advanced->value,
        TransitionAction::Completed->value,
        TransitionAction::Withdrawn->value,
    ];

    /**
     * @return array<int, array{id: int, actorName: string, badge: string, organizationName: string, summary: string, createdAt: string, href: string}>
     */
    public function latest(int $limit = self::LIMIT): array
    {
        return DocumentTransition::query()
            ->with([
                'actor:id,name',
                ...array_map(fn (string $relation) => 'document.'.$relation, DocumentDisplayTitle::relations()),
            ])
            ->whereNotIn('action', self::HIDDEN_ACTIONS)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (DocumentTransition $transition) => [
                'id' => $transition->id,
                'actorName' => $transition->actor?->name ?? 'System',
                'badge' => $this->badge($transition->action),
                'organizationName' => $transition->document->organization->name,
                'summary' => $this->summary($transition),
                'createdAt' => $transition->created_at,
                'href' => DocumentDisplayTitle::href($transition->document),
            ])
            ->values()
            ->all();
    }

    /**
     * The four badge labels the card knows. A resubmission reads as Submitted
     * (the document entered review again); no new label is invented.
     */
    private function badge(TransitionAction $action): string
    {
        return match ($action) {
            TransitionAction::Approved => 'approved',
            TransitionAction::Returned => 'returned',
            TransitionAction::Rejected => 'rejected',
            default => 'submitted',
        };
    }

    private function summary(DocumentTransition $transition): string
    {
        $title = DocumentDisplayTitle::for($transition->document);

        if ($transition->action === TransitionAction::Returned) {
            $flagged = count($transition->flagged_sections ?? []);

            return $flagged > 0
                ? $flagged.' '.Str::plural('section', $flagged).' flagged on '.$title
                : $title;
        }

        return $transition->action === TransitionAction::Resubmitted
            ? 'Revised and resubmitted '.$title
            : $title;
    }
}
