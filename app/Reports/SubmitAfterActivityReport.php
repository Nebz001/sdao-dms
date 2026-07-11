<?php

namespace App\Reports;

use App\Approval\ApprovalEngine;
use App\Attachments\AttachmentStorage;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Models\ActivityProposal;
use App\Models\AfterActivityReport;
use App\Models\Document;
use App\Models\User;
use App\Organizations\OrganizationMembershipService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An after-activity report is hard-linked to one specific APPROVED activity
 * proposal — a report cannot exist without a corresponding approved activity
 * (CLAUDE.md). Submission is gated to an active officer of the org that owns
 * the activity, and at most one non-rejected report may exist per proposal.
 */
class SubmitAfterActivityReport
{
    public function __construct(
        private readonly ApprovalEngine $engine,
        private readonly OrganizationMembershipService $membershipService,
        private readonly AttachmentStorage $attachmentStorage,
    ) {}

    /**
     * @param  array<string, UploadedFile|array<int, UploadedFile>>  $attachmentFiles
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(
        User $actor,
        ActivityProposal $proposal,
        string $summary,
        ?string $outcomes = null,
        ?int $participantCount = null,
        ?array $activityChairs = null,
        ?string $preparedBy = null,
        ?string $eventProgram = null,
        ?int $targetParticipantsPercentage = null,
        array $attachmentFiles = [],
    ): Document {
        $proposal->loadMissing('document.organization');
        $proposalDocument = $proposal->document;

        if ($proposalDocument->status !== DocumentStatus::Approved) {
            throw ValidationException::withMessages([
                'activity_proposal_id' => 'A report can only be filed against an approved activity.',
            ]);
        }

        $organization = $proposalDocument->organization;
        $membership = $this->membershipService->activeMembershipFor($actor, $organization);

        if ($membership === null) {
            throw new AuthorizationException('You must be an active officer of this organization to submit a report.');
        }

        if ($this->hasNonRejectedReport($proposal)) {
            throw ValidationException::withMessages([
                'activity_proposal_id' => 'A report has already been filed for this activity.',
            ]);
        }

        return DB::transaction(function () use (
            $actor, $proposal, $organization, $summary, $outcomes, $participantCount,
            $activityChairs, $preparedBy, $eventProgram, $targetParticipantsPercentage, $attachmentFiles
        ) {
            $document = Document::create([
                'form_type' => FormType::AfterActivityReport,
                'variant' => null,
                'title' => "After-Activity Report — {$proposal->title}",
                'status' => DocumentStatus::Draft,
                'current_step_position' => null,
                'organization_id' => $organization->id,
                'workflow_template_id' => null,
                'submitted_by' => $actor->id,
            ]);

            AfterActivityReport::create([
                'document_id' => $document->id,
                'activity_proposal_id' => $proposal->id,
                'summary' => $summary,
                'outcomes' => $outcomes,
                'participant_count' => $participantCount,
                'activity_chairs' => $activityChairs,
                'prepared_by' => $preparedBy,
                'event_program' => $eventProgram,
                'target_participants_percentage' => $targetParticipantsPercentage,
            ]);

            // Phase 2 item 8 — Photos, Sample Evaluation Form, Attendance
            // Sheet all required, no conditionals.
            $this->attachmentStorage->storeMany($document, $attachmentFiles, $actor);
            $this->attachmentStorage->assertRequiredSlotsFilled($document);

            $this->engine->submit($document, $actor);
            $document->refresh();

            return $document;
        });
    }

    /**
     * Uniqueness guard: at most one non-rejected report per activity proposal.
     * A Rejected report frees the slot (invariant #2 — reject is terminal, the
     * officer files a brand-new report), mirroring the renewal uniqueness rule.
     *
     * A report's status lives on its OWN Document, not on the
     * after_activity_reports row — so this filters via the report's document,
     * not on mere existence.
     *
     * Public so AfterActivityReportController::create() can reuse this exact
     * check when building the approved-proposal picker.
     */
    public function hasNonRejectedReport(ActivityProposal $proposal): bool
    {
        return $proposal->afterActivityReports()
            ->whereHas('document', fn ($q) => $q->where('status', '!=', DocumentStatus::Rejected->value))
            ->exists();
    }
}
