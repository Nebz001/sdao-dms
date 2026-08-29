<?php

namespace App\Printing;

use App\Enums\ActivityNature;
use App\Enums\ActivityType;
use App\Enums\OfficerPosition;
use App\Enums\Role;
use App\Identity\RoleDirectory;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Support\AcademicYear;
use App\Support\DisplayTimezone;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use LogicException;

/**
 * Printable rendering of the Activity Proposal's two source documents as one
 * PDF (Phase 2 remediation, Part 2 — the request-form docx was misidentified
 * in the original brief as the Activity Calendar submission form; its field
 * list is field-for-field CLAUDE.md's "Activity Request Form (proposal step
 * 1)"): page 1 is "STUDENT ORGANIZATION ACTIVITY REQUEST FORM"
 * (LIP-ACD-SDA-F-001), page 2+ is the proposal narrative template. One
 * submission in two steps (CLAUDE.md), one PrintableForm.
 *
 * Every role printed here — Adviser, Program Chair, Dean, Principal, SDAO,
 * Asst. Director of Academic Services, Academic Director, Executive
 * Director — genuinely is a step in this form's real digital chain (unlike
 * the registration/renewal pilot, where only SDAO is digital and
 * Adviser/Dean/CRSO are paper-only). DocumentPrintController's own docblock
 * frames the printed form as a routing artifact carried around BEFORE
 * approval to collect signatures, so every signature block shows the
 * current role-holder's name (via RoleDirectory) even before they've
 * approved — only the date/time is gated on an actual recorded approval.
 * SDAO is the one exception: names/date/time come only from stepApprovals,
 * mirroring OrganizationApplicationForm::sdaoColumn() exactly, since SDAO
 * always has exactly two named members (invariant #3) rather than one
 * role-holder to preview.
 */
class ActivityProposalForm implements PrintableForm
{
    private const string FORM_NUMBER = 'LIP-ACD-SDA-F-001';

    /**
     * Timestamps are stored/cast in UTC; every approver is Asia/Manila. See
     * OrganizationApplicationForm's identical constant/rationale.
     */
    private const string DISPLAY_TZ = DisplayTimezone::ASIA_MANILA;

    /**
     * "Nature of Activity" checkboxes, verbatim from the docx — 4 rows, maps
     * 1:1 to all 4 ActivityNature cases, no duplicates.
     *
     * @var array<int, array{label: string, case: ActivityNature|null}>
     */
    private const array NATURE_CHECKLIST = [
        ['label' => 'Co-Curricular (Activities that are connected to the academic curriculum and support learning outcomes)', 'case' => ActivityNature::CoCurricular],
        ['label' => 'Non-curricular (Activities outside the curriculum that focus on personal, social, cultural, or recreational development (e.g., sports, arts, leadership, volunteer work).', 'case' => ActivityNature::NonCurricular],
        ['label' => 'Community Extension', 'case' => ActivityNature::CommunityExtension],
        ['label' => 'Others', 'case' => ActivityNature::Others],
    ];

    /**
     * "Type of Activity" checkboxes, verbatim from the docx — 10 rows over 9
     * ActivityType cases. The docx lists BOTH "Donation Drive / Fundraising
     * Activity" AND a separate "Fundraising Activity" row; ActivityType has
     * no case for the latter. This is a verbatim transcription of the
     * client's own template, not a copy-paste bug here — same precedent as
     * OrganizationApplicationForm's duplicated "List of Past Projects" row.
     * The "Fundraising Activity" row is permanently unticked (`case: null`);
     * do not "fix" this to 9 rows, it would no longer match the source docx.
     *
     * @var array<int, array{label: string, case: ActivityType|null}>
     */
    private const array TYPE_CHECKLIST = [
        ['label' => 'Seminar/Workshop', 'case' => ActivityType::SeminarWorkshop],
        ['label' => 'General Assembly', 'case' => ActivityType::GeneralAssembly],
        ['label' => 'Orientation', 'case' => ActivityType::Orientation],
        ['label' => 'Competition', 'case' => ActivityType::Competition],
        ['label' => 'Recruitment/Audition', 'case' => ActivityType::RecruitmentAudition],
        ['label' => 'Donation Drive / Fundraising Activity', 'case' => ActivityType::DonationDriveFundraising],
        ['label' => 'Outreach', 'case' => ActivityType::Outreach],
        ['label' => 'Fundraising Activity', 'case' => null], // duplicate — see class docblock
        ['label' => 'Off-campus Activity', 'case' => ActivityType::OffCampusActivity],
        ['label' => 'Others', 'case' => null],
    ];

    public function __construct(private readonly RoleDirectory $roleDirectory) {}

    public function view(): string
    {
        return 'print.activity-proposal';
    }

    public function paper(): string
    {
        // The request-form docx is A4; the narrative docx is Letter. dompdf
        // renders one paper size per document, so both pages standardize on
        // A4 (the request form's native size) — the narrative simply
        // reflows into it.
        return 'a4';
    }

    public function orientation(): string
    {
        return 'portrait';
    }

    public function filename(Document $document): string
    {
        $slug = str($document->organization->name)->slug();

        return "activity-proposal-{$slug}-{$document->id}";
    }

    public function eagerLoads(): array
    {
        return [
            'organization.school',
            'organization.program',
            'activityProposal.calendarActivity',
            'attachments',
            'stepApprovals.user',
            'stepApprovals.workflowStep',
        ];
    }

    public function data(Document $document): array
    {
        $proposal = $document->activityProposal;

        // Mirrors OrganizationApplicationForm's null-detail guard: a
        // supported-form-type Document with no backing ActivityProposal row
        // is a data-integrity problem, not a normal state the print route
        // should render — fail as a 404, not a fatal error below.
        if ($proposal === null) {
            abort(404);
        }

        $organization = $document->organization;
        $activity = $proposal->calendarActivity;
        $isShs = $organization->belongsToSeniorHighSchool();
        // Extra-Curricular, no college (Phase 2 remediation item 3) — the
        // ExtraCurricular* workflow variants skip program chair AND dean
        // outright rather than collapsing them into one role the way SHS's
        // principal does, so there is no substitute signature to print for
        // either block.
        $hasNoSchool = $organization->hasNoSchool();

        return [
            'form_number' => self::FORM_NUMBER,
            'logo_path' => $this->logoPath(),

            // Page 1 — Activity Request Form
            'rso_name' => $organization->name,
            'title_of_activity' => $proposal->title,
            'nature_checklist' => $this->checklistRows(self::NATURE_CHECKLIST, $proposal->activity_nature),
            'type_checklist' => $this->checklistRows(self::TYPE_CHECKLIST, $proposal->activity_type),
            'partner_organizations' => $proposal->partner_organizations ?? [],
            'target_sdg' => $proposal->target_sdg !== null
                ? "SDG {$proposal->target_sdg->number()} — {$proposal->target_sdg->label()}"
                : null,
            'proposed_budget' => $proposal->proposed_budget !== null
                ? number_format((float) $proposal->proposed_budget, 2)
                : null,
            'budget_source' => $proposal->budget_source,
            'date_of_activity' => $activity?->activity_date?->format('m/d/Y'),
            'venue' => $activity?->venue,
            'tail_signatures' => [
                'sdao' => $this->sdaoSignature($document),
                'asst_director' => $this->resolveSignature(
                    $document, Role::AssistantDirectorAcademicServices,
                    fn () => $this->roleDirectory->assistantDirectorAcademicServices(),
                ),
                'academic_director' => $this->resolveSignature(
                    $document, Role::AcademicDirector,
                    fn () => $this->roleDirectory->academicDirector(),
                ),
                'executive_director' => $this->resolveSignature(
                    $document, Role::ExecutiveDirector,
                    fn () => $this->roleDirectory->executiveDirector(),
                ),
            ],

            // Page 2+ — Proposal narrative
            'is_shs' => $isShs,
            'school_name' => $organization->school?->name,
            'program_name' => $organization->program?->name,
            'academic_year' => AcademicYear::forDate($document->created_at),
            'proposed_time' => $activity !== null
                ? $this->formatTime($activity->start_time).' – '.$this->formatTime($activity->end_time)
                : null,
            'objectives' => $proposal->objectives,
            'narrative' => $proposal->narrative,
            'criteria_mechanics' => $proposal->criteria_mechanics,
            'program_flow' => $proposal->program_flow,
            'source_of_funding' => $proposal->source_of_funding,
            // Itemized expenses (client request, post-Part-2): a table +
            // grand total when the proposal has expense_items rows. Falls
            // back to the legacy `expenses` prose for proposals submitted
            // before this existed — see the model docblock. Amounts are
            // formatted here, not in Blade, mirroring how every other money
            // field (proposed_budget above, ActivityCalendarForm's budget
            // column) is formatted in the PrintableForm class.
            'expense_items' => $proposal->expense_items !== null
                ? array_map(fn (array $row) => [
                    'label' => $row['label'],
                    'amount' => number_format((float) $row['amount'], 2),
                ], $proposal->expense_items)
                : null,
            'expense_items_total' => $proposal->expenseItemsTotal,
            'expenses' => $proposal->expenses,
            'has_resource_person_resume' => $document->attachments->contains('slot_key', 'resume_of_resource_person'),
            // "VI. Responsible Person/s" has no backing field on
            // activity_proposals — DEFERRED pending client confirmation on
            // how the office intends this to be filled. Prints as a blank
            // cell, same treatment as College Dean/CRSO in the pilot. Do NOT
            // auto-fill from OrganizationMembershipService::activeOfficersFor().
            'prepared_by_president' => $this->presidentName($organization),
            'narrative_signatures' => [
                'adviser' => $this->resolveSignature(
                    $document, Role::Adviser,
                    fn () => $this->roleDirectory->adviserFor($organization),
                ),
                // SHS collapses "Reviewed by: Program Chair" + "Noted by:
                // Dean" into one "Reviewed by: Principal" block; a
                // college-less org skips both outright — its chain has
                // neither a program chair, a dean, nor a principal. Either
                // way, the program-chair/dean resolvers are only ever called
                // for a regular-school org, so their LogicException on an
                // SHS or college-less org is avoided by these branches, not
                // relied upon as the primary safety net (resolveSignature()'s
                // catch is defense in depth).
                'reviewed_by' => match (true) {
                    $hasNoSchool => null,
                    $isShs => $this->resolveSignature($document, Role::Principal, fn () => $this->roleDirectory->principalFor($organization)),
                    default => $this->resolveSignature($document, Role::ProgramChair, fn () => $this->roleDirectory->programChairFor($organization)),
                },
                'noted_by' => ($isShs || $hasNoSchool)
                    ? null
                    : $this->resolveSignature($document, Role::Dean, fn () => $this->roleDirectory->deanFor($organization)),
            ],
        ];
    }

    /**
     * Same fixed whitelist/fallback as OrganizationApplicationForm::logoPath()
     * — duplicated rather than shared, it's a 10-line, print-only, no-fail
     * lookup with no other state.
     */
    private function logoPath(): ?string
    {
        foreach (['print/nu-lipa-logo.svg', 'print/nu-lipa-logo.jpg'] as $relative) {
            $path = public_path($relative);

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{label: string, case: ActivityNature|ActivityType|null}>  $definitions
     * @return array<int, array{label: string, checked: bool}>
     */
    private function checklistRows(array $definitions, ActivityNature|ActivityType|null $selected): array
    {
        return array_map(fn (array $row) => [
            'label' => $row['label'],
            'checked' => $row['case'] !== null && $selected !== null && $row['case'] === $selected,
        ], $definitions);
    }

    private function formatTime(?string $hhmm): ?string
    {
        return $hhmm !== null ? Carbon::createFromFormat('H:i', $hhmm)->format('h:i A') : null;
    }

    /**
     * "Prepared by: [PRESIDENT'S FULL NAME]" is authorship, not an
     * approval-chain role (no Role::President case exists) — known at
     * submission time, never gated on approval. No existing shared service
     * exposes "the org's active president" directly
     * (OrganizationMembershipService::activeOfficersFor() returns both
     * officers, undistinguished), so this queries OrganizationMembership
     * directly — same precedent as OrganizationApplicationForm's
     * facultyAdviserColumn() reading stored data rather than calling a
     * shared service.
     */
    private function presidentName(Organization $organization): ?string
    {
        $membership = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->where('position', OfficerPosition::President)
            ->with('user')
            ->first();

        return $membership?->user->name;
    }

    /**
     * SDAO always has exactly two named members (invariant #3) — identical
     * to OrganizationApplicationForm::sdaoColumn(): names/date/time come
     * only from stepApprovals, no RoleDirectory preview.
     */
    private function sdaoSignature(Document $document): SignatureBlock
    {
        $approvals = $document->stepApprovals
            ->filter(fn ($approval) => $approval->workflowStep->role === Role::SdaoMember)
            ->sortBy('created_at')
            ->values();

        if ($approvals->isEmpty()) {
            return SignatureBlock::blank(Role::SdaoMember->label());
        }

        $latest = $approvals->last()->created_at->setTimezone(self::DISPLAY_TZ);

        return new SignatureBlock(
            roleLabel: Role::SdaoMember->label(),
            names: $approvals->map(fn ($approval) => $approval->user->name)->all(),
            date: $latest->format('m/d/Y'),
            time: $latest->format('h:i A'),
        );
    }

    /**
     * For every single-approver role: prints the CURRENT role-holder's name
     * (via RoleDirectory) even before they've approved, so the artifact
     * printed pre-approval for a physical signature-collection walk shows
     * whose signature to collect — but date/time are only ever set from an
     * actual recorded stepApprovals row, never fabricated from "who
     * currently holds the role." RoleDirectory throwing
     * ModelNotFoundException (unfilled role) or LogicException (wrong org
     * type for this role) degrades to a blank name, never a 500 — the
     * literal "must degrade to a blank column" requirement.
     */
    private function resolveSignature(Document $document, Role $role, \Closure $currentHolder): SignatureBlock
    {
        $roleLabel = $role->label();

        $approval = $document->stepApprovals
            ->filter(fn ($a) => $a->workflowStep->role === $role)
            ->sortBy('created_at')
            ->last();

        if ($approval !== null) {
            $date = $approval->created_at->setTimezone(self::DISPLAY_TZ);

            return new SignatureBlock(
                roleLabel: $roleLabel,
                names: [$approval->user->name],
                date: $date->format('m/d/Y'),
                time: $date->format('h:i A'),
            );
        }

        try {
            $currentHolderName = $currentHolder()->name;
        } catch (ModelNotFoundException|LogicException) {
            $currentHolderName = null;
        }

        if ($currentHolderName !== null) {
            return new SignatureBlock(roleLabel: $roleLabel, names: [$currentHolderName], date: null, time: null);
        }

        return SignatureBlock::blank($roleLabel);
    }
}
