<?php

namespace App\ActivityProposals;

use App\Approval\ApprovalEngine;
use App\Approval\StepApproverGuard;
use App\Calendar\VenueConflictChecker;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\User;
use App\Organizations\OrganizationMembershipService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitActivityProposal
{
    public function __construct(
        private readonly ApprovalEngine $engine,
        private readonly VenueConflictChecker $conflictChecker,
        private readonly ProposalVariantResolver $variantResolver,
        private readonly OrganizationMembershipService $membershipService,
        private readonly StepApproverGuard $approverGuard,
        private readonly OnCalendarActivityLockChecker $activityLockChecker,
    ) {}

    /**
     * Submit a Draft proposal to the approval chain (step-2 completion).
     *
     * Computes the correct ProposalVariant from the org's school structure and
     * calendar_mode, sets it on the document before engine.submit(), which then
     * resolves the seeded workflow template.
     *
     * @return array{document: Document, warnings: array<int, mixed>}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(
        User $actor,
        Document $document,
        ?string $objectives = null,
        ?string $activityDescription = null,
        ?string $criteriaMechanics = null,
        ?string $programFlow = null,
        ?array $expenseItems = null,
        ?array $responsiblePersons = null,
    ): array {
        if ($document->status !== DocumentStatus::Draft) {
            throw new AuthorizationException('Only Draft documents can be submitted to the chain.');
        }

        if (! $this->membershipService->canActOnDocument($actor, $document)) {
            throw new AuthorizationException('Only an active officer of this organization may submit this document.');
        }

        $document->load(['organization', 'activityProposal.calendarActivity']);
        $proposal = $document->activityProposal;

        // Hard-block: off-calendar activity must not overlap an already-Approved slot.
        // On-calendar instead re-checks that nobody else's proposal claimed
        // this same activity while this Draft was being written — the
        // dropdown already filters locked activities out, but this is the
        // authoritative re-check that actually closes the race.
        if ($proposal->calendar_mode === ProposalCalendarMode::OffCalendar) {
            $this->guardConfirmedConflicts($proposal->calendarActivity, null);
        } else {
            $this->guardActivityNotLocked($proposal->calendarActivity, $document->id);
        }

        $variant = $this->variantResolver->resolve($document->organization, $proposal->calendar_mode);

        $this->approverGuard->assertCanSubmit(FormType::ActivityProposal, $variant, $document->organization);

        $document = DB::transaction(function () use (
            $actor, $document, $proposal, $variant, $objectives, $activityDescription,
            $criteriaMechanics, $programFlow, $expenseItems, $responsiblePersons,
        ) {
            // proposed_budget (and the other step-1 exact fields) are
            // intentionally NOT touched here — they're set once at step 1
            // (Phase 2 item 7 slice 4a) and never re-collected at step 2.
            // source_of_funding no longer exists (Group D item 4) — step 2
            // echoes step 1's budget_source_label read-only instead.
            $proposal->update([
                'objectives' => $objectives,
                // Group E backlog — Activity Description restored.
                'activity_description' => $activityDescription,
                // Exact field corrections (Phase 2 item 7 slice 4b).
                'criteria_mechanics' => $criteriaMechanics,
                'program_flow' => $programFlow,
                // Itemized expenses (client request, post-Part-2) — legacy
                // `expenses` prose is intentionally never rewritten here,
                // see App\Models\ActivityProposal's docblock. Group D item 3
                // — rows are {material, quantity, unit_price}.
                'expense_items' => $expenseItems,
                'responsible_persons' => $responsiblePersons,
                // Snapshotted once, here, at submission — never rewritten on
                // resubmit (ResubmitActivityProposal's field allowlist
                // omits it by design). See ActivityProposal's docblock and
                // App\Printing\ActivityProposalForm, which reads this
                // instead of live-querying the org's currently-active
                // president.
                'president_name' => $this->membershipService->activePresidentFor($document->organization)?->name,
            ]);

            $document->variant = $variant;
            $document->save();

            $this->engine->submit($document, $actor);
            $document->refresh();

            return $document;
        });

        // Non-blocking tentative warnings (after submit so excludeDocumentId works).
        $warnings = [];
        if ($proposal->calendar_mode === ProposalCalendarMode::OffCalendar) {
            $warnings = $this->collectTentativeWarnings($proposal->calendarActivity, $document->id);
        }

        return ['document' => $document, 'warnings' => $warnings];
    }

    /** @throws ValidationException */
    private function guardActivityNotLocked(CalendarActivity $activity, int $excludeDocumentId): void
    {
        if ($this->activityLockChecker->isLocked($activity, $excludeDocumentId)) {
            throw ValidationException::withMessages([
                'calendar_activity_id' => 'This activity now has another active proposal — someone else claimed it while you were completing this one.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function guardConfirmedConflicts(CalendarActivity $activity, ?int $excludeDocumentId): void
    {
        $conflicts = $this->conflictChecker->confirmedConflicts(
            $activity->venue,
            $activity->activity_date->toDateString(),
            $activity->start_time,
            $activity->end_time,
            $excludeDocumentId,
        );

        if ($conflicts->isNotEmpty()) {
            $names = $conflicts->map(fn ($c) => "\"{$c->name}\" ({$c->calendar->document->organization->name})")->implode(', ');

            throw ValidationException::withMessages([
                'activity' => "This activity conflicts with an already-approved booking: {$names}.",
            ]);
        }
    }

    /** @return array<int, mixed> */
    private function collectTentativeWarnings(CalendarActivity $activity, int $excludeDocumentId): array
    {
        $conflicts = $this->conflictChecker->tentativeConflicts(
            $activity->venue,
            $activity->activity_date->toDateString(),
            $activity->start_time,
            $activity->end_time,
            $excludeDocumentId,
        );

        if ($conflicts->isEmpty()) {
            return [];
        }

        return [[
            'conflicts' => $conflicts->map(fn ($c) => [
                'name' => $c->name,
                'venue' => $c->venue,
                'activity_date' => $c->activity_date->toDateString(),
                'start_time' => $c->start_time,
                'end_time' => $c->end_time,
                'organization' => $c->calendar->document->organization->name,
            ])->values()->all(),
        ]];
    }
}
