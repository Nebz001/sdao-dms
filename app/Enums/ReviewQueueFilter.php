<?php

namespace App\Enums;

/**
 * The ?filter= query string modes ActivityProposalReviewController::index()
 * accepts. Overdue narrows the live "at my step" queue; the other three
 * switch it to this approver's own decision history for the current
 * academic year (see ApprovalEngine's docblock for why each transition's
 * actor_id is the deciding individual).
 */
enum ReviewQueueFilter: string
{
    case Overdue = 'overdue';
    case Approved = 'approved';
    case Returned = 'returned';
    case Decided = 'decided';

    public function label(): string
    {
        return match ($this) {
            self::Overdue => 'Overdue',
            self::Approved => 'Approved',
            self::Returned => 'Returned',
            self::Decided => 'All decisions',
        };
    }

    /**
     * False only for Overdue, which still narrows the LIVE queue rather
     * than switching to decision history.
     */
    public function isHistory(): bool
    {
        return $this !== self::Overdue;
    }

    /**
     * @return array<int, TransitionAction>
     */
    public function actions(): array
    {
        return match ($this) {
            self::Approved => [TransitionAction::Approved],
            self::Returned => [TransitionAction::Returned],
            self::Decided => [TransitionAction::Approved, TransitionAction::Returned, TransitionAction::Rejected],
            self::Overdue => [],
        };
    }
}
