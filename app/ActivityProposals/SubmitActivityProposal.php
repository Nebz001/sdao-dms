<?php

namespace App\ActivityProposals;

use App\Approval\ApprovalEngine;
use App\Calendar\VenueConflictChecker;
use App\Enums\DocumentStatus;
use App\Enums\ProposalCalendarMode;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitActivityProposal
{
    public function __construct(
        private readonly ApprovalEngine $engine,
        private readonly VenueConflictChecker $conflictChecker,
        private readonly ProposalVariantResolver $variantResolver,
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
        string $objectives,
        string $narrative,
        ?string $criteriaMechanics = null,
        ?string $programFlow = null,
        ?string $sourceOfFunding = null,
        ?string $expenses = null,
    ): array {
        if ($document->status !== DocumentStatus::Draft) {
            throw new AuthorizationException('Only Draft documents can be submitted to the chain.');
        }

        if ($document->submitted_by !== $actor->id) {
            throw new AuthorizationException('Only the original submitter may submit this document.');
        }

        $document->load(['organization', 'activityProposal.calendarActivity']);
        $proposal = $document->activityProposal;

        // Hard-block: off-calendar activity must not overlap an already-Approved slot.
        // (On-calendar references an Approved activity that already hard-blocks — no check needed.)
        if ($proposal->calendar_mode === ProposalCalendarMode::OffCalendar) {
            $this->guardConfirmedConflicts($proposal->calendarActivity, null);
        }

        $variant = $this->variantResolver->resolve($document->organization, $proposal->calendar_mode);

        $document = DB::transaction(function () use (
            $actor, $document, $proposal, $variant, $objectives, $narrative,
            $criteriaMechanics, $programFlow, $sourceOfFunding, $expenses,
        ) {
            // proposed_budget (and the other step-1 exact fields) are
            // intentionally NOT touched here — they're set once at step 1
            // (Phase 2 item 7 slice 4a) and never re-collected at step 2.
            $proposal->update([
                'objectives' => $objectives,
                'narrative' => $narrative,
                // Exact field corrections (Phase 2 item 7 slice 4b).
                'criteria_mechanics' => $criteriaMechanics,
                'program_flow' => $programFlow,
                'source_of_funding' => $sourceOfFunding,
                'expenses' => $expenses,
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
