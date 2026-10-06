<?php

namespace App\Approval;

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Identity\RoleDirectory;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * "What's currently waiting at my step" — the shared logic behind the
 * approver dashboard's queue-based figures and
 * ActivityProposalReviewController's queue, so the two can never disagree.
 *
 * Deliberately no role-based prefilter: every InReview document is always
 * resolved (and logged on failure), regardless of its current step's role
 * — ProgramChairResolutionFailureTest depends on a document sitting at a
 * misconfigured step still being resolved (and logged), not silently
 * skipped because its role doesn't match the caller.
 */
class ApproverQueue
{
    /** The one place the "waiting too long" threshold lives. */
    public const int OVERDUE_AFTER_DAYS = 3;

    /** Below this, a document's wait badge reads neutral; at/above, amber (see waitTier()). */
    public const int WARNING_AFTER_DAYS = 2;

    /** How close an activity's date must be to count as "urgent"/"upcoming". */
    public const int EVENT_SOON_DAYS = 7;

    public function __construct(
        private readonly StepApproverResolver $resolver,
        private readonly RoleDirectory $directory,
    ) {}

    /**
     * Every InReview document whose CURRENT step resolves to $user, across
     * the given form type (or every form type when null). Bulk-fetches and
     * filters in PHP — the resolver has no SQL-expressible equivalent — but
     * wraps seat resolution in RoleDirectory::remembering() so resolving the
     * same seat (e.g. the same organization's adviser) across many documents
     * costs one query, not one per document.
     *
     * @param  array<int, string>  $with  extra relations to eager-load alongside the ones this method already needs
     * @return Collection<int, Document>
     */
    public function pendingFor(User $user, ?FormType $formType = null, array $with = []): Collection
    {
        $relations = array_values(array_unique([
            'organization',
            'workflowTemplate.steps',
            'latestTransition',
            ...$with,
        ]));

        /** @var Collection<int, Document> $documents */
        $documents = $this->directory->remembering(fn () => Document::query()
            ->with($relations)
            ->where('status', DocumentStatus::InReview->value)
            ->when($formType, fn ($q) => $q->where('form_type', $formType->value))
            ->orderBy('created_at')
            ->get()
            ->filter(function (Document $d) use ($user) {
                try {
                    $step = $d->workflowTemplate?->steps
                        ->firstWhere('position', $d->current_step_position);

                    return $step !== null && $this->resolver->approversFor($step, $d)->contains('id', $user->id);
                } catch (\Throwable $e) {
                    Log::error('Approver resolution failed while filtering queue', ['exception' => $e->getMessage()]);

                    return false;
                }
            })
            ->values());

        return $documents;
    }

    /**
     * The transition that made this document's current step actually become
     * active — the same clock AdminDashboardController::oldestInReview()
     * trusts, exposed as the whole transition (not just its timestamp) so a
     * caller can also read *how* the wait began (a fresh Submitted, an
     * ordinary Advanced from the step below, or a Resubmitted after the
     * student revised and sent it back — worth flagging, since the document
     * may have changed since this approver last saw it). Correct for this
     * purpose specifically because every step a non-SDAO approver ever sits
     * at has required_approvals = 1 (WorkflowTemplateSeeder): there is no
     * partial-quorum event that could land between the step activating and
     * this approver's own decision, unlike SDAO's 2-required step.
     *
     * Only ever called (via pendingFor()'s results) on an InReview document,
     * which by construction always has at least one transition —
     * ApprovalEngine::submit() records the Submitted transition in the same
     * transaction that sets status to InReview — so `latestTransition` is
     * never actually null here despite its nullable relation type.
     */
    public static function waitingSinceTransition(Document $document): DocumentTransition
    {
        return $document->latestTransition;
    }

    public static function waitingSince(Document $document): CarbonInterface
    {
        return self::waitingSinceTransition($document)->created_at;
    }

    /**
     * @return 'normal'|'warning'|'overdue'
     */
    public static function waitTier(Document $document): string
    {
        $since = self::waitingSince($document);

        if ($since->lt(now()->subDays(self::OVERDUE_AFTER_DAYS))) {
            return 'overdue';
        }

        if ($since->lte(now()->subDays(self::WARNING_AFTER_DAYS))) {
            return 'warning';
        }

        return 'normal';
    }
}
