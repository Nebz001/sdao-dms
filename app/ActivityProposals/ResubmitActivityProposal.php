<?php

namespace App\ActivityProposals;

use App\Approval\ApprovalEngine;
use App\Approval\FieldChangeSet;
use App\Approval\SectionFields;
use App\Approval\SectionFlags;
use App\Attachments\AttachmentStorage;
use App\Calendar\VenueConflictChecker;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\User;
use App\Organizations\OrganizationMembershipService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResubmitActivityProposal
{
    public function __construct(
        private readonly ApprovalEngine $engine,
        private readonly VenueConflictChecker $conflictChecker,
        private readonly OrganizationMembershipService $membershipService,
        private readonly AttachmentStorage $attachmentStorage,
    ) {}

    /**
     * Edit and resubmit a Returned proposal, resuming at the returning approver.
     *
     * Accepts updated narrative fields. For off-calendar proposals the activity
     * details (venue/date/times) can also be updated; the conflict checker
     * re-runs against the new values (excluding the document's own rows).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile|array<int, UploadedFile>>  $attachmentFiles
     * @return array{document: Document, warnings: array<int, mixed>}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $actor, Document $document, array $data, array $attachmentFiles = []): array
    {
        if ($document->status !== DocumentStatus::Returned) {
            throw new AuthorizationException('Only Returned documents can be resubmitted.');
        }

        if (! $this->membershipService->canActOnDocument($actor, $document)) {
            throw new AuthorizationException('Only an active officer of this organization may resubmit this document.');
        }

        $document->load(['activityProposal.calendarActivity']);
        $proposal = $document->activityProposal;
        $isOffCalendar = $proposal->calendar_mode === ProposalCalendarMode::OffCalendar;

        // ── Field-level revision diffs: snapshot BOTH models up front ──────
        //
        // Both writes below mutate their model in place ($activity->update()
        // just below and $proposal->update() inside the transaction), and
        // Model::update() -> save() -> syncOriginal() means getOriginal()
        // would return the NEW values afterwards. So the only correct moment
        // for the before-side is here, before either write.
        //
        // 'schedule_venue' is the one section whose fields live on
        // CalendarActivity; it is excluded entirely for ON-calendar
        // proposals, where those fields are structurally uneditable —
        // reporting "no changes" about a field the student cannot touch
        // would be misleading, not just noise.
        //
        // The two field-key sets are disjoint (proposal: title,
        // activity_nature, activity_type, partner_organizations, target_sdg,
        // objectives, activity_description, criteria_mechanics, program_flow,
        // expense_items, responsible_persons, proposed_budget, budget_source;
        // activity: venue, activity_date, start_time, end_time), so merging
        // them into one flat snapshot is safe.
        $flagged = SectionFlags::currentlyFlagged($document);

        if (! $isOffCalendar) {
            $flagged = array_values(array_diff($flagged, ['schedule_venue']));
        }

        $activityDefs = in_array('schedule_venue', $flagged, true)
            ? SectionFields::definitionsFor(FormType::ActivityProposal, 'schedule_venue')
            : [];
        $proposalDefs = SectionFields::definitionsForSections(
            FormType::ActivityProposal,
            array_values(array_diff($flagged, ['schedule_venue'])),
        );
        // Group C item 3: an attachment-only flagged key (request_letter,
        // resume_of_resource_person, sample_post_survey_form) has no scalar
        // FieldDefinition — $activityDefs/$proposalDefs alone would miss it.
        // FieldChangeSet::build() already handles attachment-slot keys via
        // its own AttachmentSlots lookup, so "anything flagged at all" is
        // the correct, simpler gate — it silently no-ops for any key with
        // neither scalar defs nor a matching slot.
        $trackAnything = $flagged !== [];

        $oldValues = $trackAnything
            ? array_merge(
                FieldChangeSet::snapshot($proposal, $proposalDefs),
                FieldChangeSet::snapshot($proposal->calendarActivity, $activityDefs),
            )
            : [];

        // Step-1 attachment slots (Group C item 3) are now Mode A and
        // section-flaggable like every other form type's — snapshot BEFORE
        // AttachmentStorage::storeMany() runs below, same as every other
        // resubmit action's own attachment-presence snapshot.
        $hadAttachmentsBefore = FieldChangeSet::snapshotAttachmentPresence($document, $flagged);

        // For off-calendar, optionally update the CalendarActivity details first.
        if ($isOffCalendar) {
            $activity = $proposal->calendarActivity;

            if ($activity !== null && $this->hasActivityUpdate($data)) {
                $activity->update(array_filter([
                    'name' => $data['title'] ?? $activity->name,
                    'venue' => $data['venue'] ?? $activity->venue,
                    'activity_date' => $data['activity_date'] ?? $activity->activity_date->toDateString(),
                    'start_time' => $data['start_time'] ?? $activity->start_time,
                    'end_time' => $data['end_time'] ?? $activity->end_time,
                ], fn ($v) => $v !== null));

                $activity->refresh();
            }

            // Hard-block: re-check against Approved slots (exclude self — document is Returned).
            if ($activity !== null) {
                $this->guardConfirmedConflicts($activity, $document->id);
            }
        }

        $document = DB::transaction(function () use (
            $actor, $document, $proposal, $data, $flagged, $proposalDefs, $activityDefs, $oldValues, $trackAnything,
            $attachmentFiles, $hadAttachmentsBefore,
        ) {
            $proposal->update([
                'objectives' => $data['objectives'],
                // Group E backlog — Activity Description restored.
                'activity_description' => $data['activity_description'],
                // Exact field corrections (Phase 2 item 7 slice 4b).
                'criteria_mechanics' => $data['criteria_mechanics'],
                'program_flow' => $data['program_flow'],
                // Itemized expenses (client request, post-Part-2) — legacy
                // `expenses` prose is intentionally never rewritten here,
                // see App\Models\ActivityProposal's docblock. Group D item 3
                // — rows are {material, quantity, unit_price}.
                'expense_items' => $data['expense_items'],
                'responsible_persons' => $data['responsible_persons'],
                'proposed_budget' => $data['proposed_budget'] ?? $proposal->proposed_budget,
                // Exact field corrections (Phase 2 item 7 slice 4a) — editable
                // on resubmission, same as proposed_budget already is.
                'activity_nature' => $data['activity_nature'] ?? $proposal->activity_nature,
                'activity_nature_other' => $data['activity_nature_other'] ?? $proposal->activity_nature_other,
                'activity_type' => $data['activity_type'] ?? $proposal->activity_type,
                'activity_type_other' => $data['activity_type_other'] ?? $proposal->activity_type_other,
                'partner_organizations' => $data['partner_organizations'] ?? $proposal->partner_organizations,
                'target_sdg' => $data['target_sdg'] ?? $proposal->target_sdg,
                'budget_source' => $data['budget_source'] ?? $proposal->budget_source,
            ]);

            if (isset($data['title'])) {
                $proposal->update(['title' => $data['title']]);
            }

            // Step-1 attachments (Group C item 3) — only newly re-uploaded
            // slots are in $attachmentFiles; untouched slots from the
            // original submission are left in place.
            // assertRequiredSlotsFilled checks persisted rows + anything
            // just stored, so completeness still holds.
            $this->attachmentStorage->storeMany($document, $attachmentFiles, $actor);
            $this->attachmentStorage->assertRequiredSlotsFilled($document);

            // Both models were updated in place and are already current
            // ($activity->refresh() ran above, before this transaction
            // opened; $proposal->update() just wrote through this same
            // instance), so the after-side reads straight off them — no
            // re-query needed here, unlike the query-builder mass-update
            // paths in the other four action classes.
            $fieldChanges = $trackAnything ? FieldChangeSet::build(
                $document->form_type,
                $flagged,
                $oldValues,
                array_merge(
                    FieldChangeSet::snapshot($proposal, $proposalDefs),
                    FieldChangeSet::snapshot($proposal->calendarActivity, $activityDefs),
                ),
                $hadAttachmentsBefore,
                $attachmentFiles,
            ) : null;

            $this->engine->resubmit($document, $actor, $fieldChanges);
            $document->refresh();

            return $document;
        });

        // Non-blocking tentative warnings after resubmit.
        $warnings = [];
        if ($proposal->calendar_mode === ProposalCalendarMode::OffCalendar && $proposal->calendarActivity !== null) {
            $warnings = $this->collectTentativeWarnings($proposal->calendarActivity, $document->id);
        }

        return ['document' => $document, 'warnings' => $warnings];
    }

    /** @param  array<string, mixed>  $data */
    private function hasActivityUpdate(array $data): bool
    {
        return isset($data['title']) || isset($data['venue']) || isset($data['activity_date'])
            || isset($data['start_time']) || isset($data['end_time']);
    }

    /** @throws ValidationException */
    private function guardConfirmedConflicts(CalendarActivity $activity, int $excludeDocumentId): void
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
