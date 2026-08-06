<?php

namespace App\Printing;

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Enums\Role;
use App\Models\Document;
use App\Models\OrganizationRegistrationDetail;
use App\Support\AcademicYear;
use Illuminate\Support\Collection;

/**
 * Printable rendering of NU Lipa's "STUDENT ORGANIZATION APPLICATION FORM"
 * (ACO – SA – F – 002, ver 2019) — serves BOTH Organization Registration and
 * Organization Renewal, exactly as the one physical form does; the NEW/
 * RENEWAL checkbox is the only discriminator (documents.form_type).
 *
 * Endorsements: only SDAO has real approval data in this system (the
 * digital chain is deliberately SDAO-only, CLAUDE.md "Registration &
 * renewal — digital scope"). Faculty Adviser prints a name (adviser_id) with
 * a blank date/time; College Dean and CRSO print fully blank for wet
 * signature. §5 PROBATION, §6 Remarks, and both checklists' "Others" row
 * have no data-model equivalent and always print blank — this is a
 * deliberate scope decision, not an oversight; do not backfill it here.
 */
class OrganizationApplicationForm implements PrintableForm
{
    private const string FORM_CODE = 'ACO – SA – F – 002';

    private const string FORM_VERSION = 'ver 2019';

    /**
     * Timestamps are stored/cast in UTC (config/app.php has no
     * APP_TIMEZONE); the office and every approver are Asia/Manila. Format
     * every printed Date:/Time: through this one constant rather than
     * scattering timezone conversions across the form.
     */
    private const string DISPLAY_TZ = 'Asia/Manila';

    /**
     * §7 checklist, verbatim from the paper form — deliberately NOT sourced
     * from App\Attachments\AttachmentSlots: the paper wording, ordering, and
     * the unmodeled "Others" row differ from the upload registry.
     *
     * The RENEWAL column lists "List of Past Projects" TWICE on the real
     * physical form (ACO-SA-F-002) — this is not a copy-paste bug here, it
     * is a verbatim transcription of a printing error on the client's own
     * form. Both rows tick together since they map to the same slot. Do not
     * "fix" this to a single row — it would no longer match the paper form
     * this feature exists to reproduce.
     *
     * @var array<int, array{label: string, slot: string|null}>
     */
    private const array NEW_CHECKLIST = [
        ['label' => 'Letter of Intent', 'slot' => 'letter_of_intent'],
        ['label' => 'Application Form', 'slot' => 'application_form'],
        ['label' => 'By Laws of the Organization', 'slot' => 'by_laws'],
        ['label' => 'Updated List of Officers/Founders', 'slot' => 'officers_list'],
        ['label' => 'Letter from the College Dean endorsing the Faculty Adviser (Co-Curricular organizations)', 'slot' => 'dean_endorsement_letter'],
        ['label' => 'List of Proposed Projects with Proposed Budget for the AY', 'slot' => 'proposed_projects_budget'],
        ['label' => 'Others', 'slot' => null],
    ];

    /**
     * @var array<int, array{label: string, slot: string|null}>
     */
    private const array RENEWAL_CHECKLIST = [
        ['label' => 'Letter of Intent', 'slot' => 'letter_of_intent'],
        ['label' => 'Application Form', 'slot' => 'application_form'],
        ['label' => 'By Laws of the Organization (if there is an update done last AY)', 'slot' => 'by_laws'],
        ['label' => 'Updated List of Officers/Founders for the AY', 'slot' => 'officers_list'],
        ['label' => 'Letter from the College Dean endorsing the Faculty Adviser (Co-Curricular organizations)', 'slot' => 'dean_endorsement_letter'],
        ['label' => 'List of Proposed Projects with Proposed Budget for the AY', 'slot' => 'proposed_projects_budget'],
        ['label' => 'List of Past Projects', 'slot' => 'past_projects_list'],
        ['label' => 'Financial Statement of the previous AY', 'slot' => 'financial_statement'],
        ['label' => 'List of Past Projects', 'slot' => 'past_projects_list'], // duplicate — see class docblock
        ['label' => 'Summary of Evaluation of the Past Projects', 'slot' => 'evaluation_summary'],
        ['label' => 'Others', 'slot' => null],
    ];

    public function view(): string
    {
        return 'print.organization-application';
    }

    public function paper(): string
    {
        return 'a4';
    }

    public function orientation(): string
    {
        return 'portrait';
    }

    public function filename(Document $document): string
    {
        $slug = str($document->organization->name)->slug();

        return "organization-application-{$slug}-{$document->id}";
    }

    public function eagerLoads(): array
    {
        return [
            'organization.school',
            'organization.program',
            'registrationDetail.adviser',
            'attachments',
            'stepApprovals.user',
            'stepApprovals.workflowStep',
        ];
    }

    public function data(Document $document): array
    {
        $detail = $document->registrationDetail;

        // Registration/Renewal Documents are only ever created together with
        // their detail row (SubmitOrganizationRegistration/Renewal, one
        // transaction — there is no interim draft phase for this form type,
        // unlike ActivityProposal's two-step flow). A null detail here means
        // a data-integrity problem, not a normal state the print route
        // should render; fail as a 404 like an unsupported form type, not a
        // fatal error on the unguarded property access below.
        if ($detail === null) {
            abort(404);
        }

        $organization = $document->organization;
        $isRenewal = $document->form_type === FormType::OrganizationRenewal;
        $uploadedSlots = $document->attachments->pluck('slot_key')->unique();

        return [
            'form_code' => self::FORM_CODE,
            'form_version' => self::FORM_VERSION,
            'is_new' => ! $isRenewal,
            'is_renewal' => $isRenewal,
            'academic_year' => $detail->academic_year ?? AcademicYear::forDate($document->created_at),
            'organization_name' => $organization->name,
            'college' => $organization->school?->name,
            'program' => $organization->program?->name,
            'contact_person' => $detail->contact_person,
            'contact_no' => $detail->contact_no,
            'email_address' => $detail->email_address,
            'date_organized' => $detail->date_organized->format('m/d/Y'),
            'purpose' => $detail->purpose_of_organization,
            'is_co_curricular' => $detail->organization_type === OrganizationType::CoCurricular,
            'is_extra_curricular' => $detail->organization_type === OrganizationType::ExtraCurricular,
            'checklist' => [
                'new' => $this->checklistRows(self::NEW_CHECKLIST, $isRenewal ? null : $uploadedSlots),
                'renewal' => $this->checklistRows(self::RENEWAL_CHECKLIST, $isRenewal ? $uploadedSlots : null),
            ],
            'endorsements' => [
                'faculty_adviser' => $this->facultyAdviserColumn($detail),
                'college_dean' => EndorsementColumn::blank(),
                'sdao' => $this->sdaoColumn($document),
                'crso' => EndorsementColumn::blank(),
            ],
            'approval' => $this->approvalSection($document),
            'remarks' => '',
            'logo_path' => $this->logoPath(),
        ];
    }

    /**
     * Resolves the NU Lipa letterhead asset from a fixed whitelist, or null
     * if none has been supplied — the template must render without a logo
     * rather than fail (no asset is committed to the repo). SVG preferred
     * (renders without ext-gd, which is disabled in this environment);
     * JPEG as a fallback. Deliberately not an alpha PNG — that needs GD.
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
     * @param  array<int, array{label: string, slot: string|null}>  $definitions
     * @param  Collection<int, string>|null  $uploadedSlots
     * @return array<int, array{label: string, checked: bool}>
     */
    private function checklistRows(array $definitions, ?Collection $uploadedSlots): array
    {
        return array_map(fn (array $row) => [
            'label' => $row['label'],
            'checked' => $row['slot'] !== null && $uploadedSlots !== null && $uploadedSlots->contains($row['slot']),
        ], $definitions);
    }

    private function facultyAdviserColumn(OrganizationRegistrationDetail $detail): EndorsementColumn
    {
        if ($detail->adviser === null) {
            return EndorsementColumn::blank();
        }

        return new EndorsementColumn(names: [$detail->adviser->name], date: null, time: null);
    }

    private function sdaoColumn(Document $document): EndorsementColumn
    {
        $approvals = $document->stepApprovals
            ->filter(fn ($approval) => $approval->workflowStep->role === Role::SdaoMember)
            ->sortBy('created_at')
            ->values();

        if ($approvals->isEmpty()) {
            return EndorsementColumn::blank();
        }

        $latest = $approvals->last()->created_at->setTimezone(self::DISPLAY_TZ);

        return new EndorsementColumn(
            names: $approvals->map(fn ($approval) => $approval->user->name)->all(),
            date: $latest->format('m/d/Y'),
            time: $latest->format('h:i A'),
        );
    }

    /**
     * @return array{is_approved: bool, is_probation: bool, sdao_date: string|null, crso_date: string|null}
     */
    private function approvalSection(Document $document): array
    {
        $isApproved = $document->status === DocumentStatus::Approved;

        return [
            'is_approved' => $isApproved,
            'is_probation' => false,
            'sdao_date' => $isApproved ? $this->sdaoColumn($document)->date : null,
            'crso_date' => null,
        ];
    }
}
