<?php

namespace Database\Seeders;

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Attachments\AttachmentSlots;
use App\Calendar\SubmitActivityCalendar;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OfficerPosition;
use App\Enums\OrganizationType;
use App\Enums\ProposalCalendarMode;
use App\Enums\Role;
use App\Models\ActivityProposal;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationRegistrationDetail;
use App\Models\Program;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Organizations\BindOrganizationOfficer;
use App\Registrations\ApproveOrganizationRegistration;
use App\Registrations\SubmitOrganizationRegistration;
use App\Renewals\SubmitOrganizationRenewal;
use App\Reports\SubmitAfterActivityReport;
use App\Support\AcademicYear;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Builds a realistic, browsable demo dataset on top of the preserved user
 * roster: real NU Lipa organizations, founded and documented through the
 * actual registration/renewal/calendar/proposal/report action classes (never
 * by hand-setting status/current_step_position), covering every form type in
 * every status the real UI can produce.
 *
 * Two form types (Registration, Renewal) have no "save as draft" entry point
 * in the real app — SubmitOrganizationRegistration/SubmitOrganizationRenewal
 * always call ApprovalEngine::submit() in the same transaction that creates
 * the Document. Their Draft examples here are built by replicating that same
 * pre-submit code shape (Organization + Document + OrganizationRegistrationDetail,
 * matching field-for-field) and simply stopping before the submit() call —
 * not a fabricated shape, just the real one halted one step earlier.
 *
 * SDAO approvals always use the two real, named SDAO members (Carl Justin
 * Magpantay, Zaira Joy Enayo) rather than App\Identity\RoleDirectory::
 * sdaoMembers(), which — because of pre-existing IdentitySeeder pollution
 * left untouched by design (see ResetDemoData's docblock) — would also
 * return two placeholder "SDAO Member A/B" accounts.
 */
class DemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    private const string PASSWORD = 'ict@1234';

    private User $carl;

    private User $zaira;

    private User $assistantDirector;

    private User $academicDirector;

    private User $executiveDirector;

    private int $idNumberSeq = 300001;

    /** @var array<string, int> */
    private array $summary = [];

    public function __construct(
        private readonly ApprovalEngine $engine,
        private readonly SubmitOrganizationRegistration $submitRegistration,
        private readonly ApproveOrganizationRegistration $approveRegistration,
        private readonly SubmitOrganizationRenewal $submitRenewal,
        private readonly SubmitActivityCalendar $submitCalendar,
        private readonly StartProposalDraft $startProposalDraft,
        private readonly SubmitActivityProposal $submitProposal,
        private readonly SubmitAfterActivityReport $submitReport,
        private readonly BindOrganizationOfficer $bindOfficer,
    ) {}

    /**
     * @return array<string, int>
     */
    public function run(): array
    {
        // Demo attachments are throwaway fakes (fakeAttachmentsFor() below) —
        // keep them off real Supabase storage regardless of what
        // filesystems.attachments/ATTACHMENTS_DISK resolves to.
        config(['filesystems.attachments' => 'local']);

        $this->resolveFixedApprovers();

        $accounts = $this->seedAccounts();
        $orgs = $this->foundOrganizations($accounts);
        $this->bindSecretaries($orgs, $accounts);
        $this->seedRegistrationStatusSpread($accounts);
        $this->seedRenewalStatusSpread($orgs);
        $calendarActivities = $this->seedActivityCalendars($orgs);
        $proposals = $this->seedActivityProposals($orgs, $calendarActivities);
        $this->seedAfterActivityReports($proposals);
        $this->markSomeNotificationsRead($accounts, $orgs);

        return $this->summary;
    }

    // -------------------------------------------------------------------------
    // Fixed approvers (already-seeded real staff — resolved by email, never
    // by RoleDirectory::sdaoMembers() for SDAO — see class docblock).
    // -------------------------------------------------------------------------

    private function resolveFixedApprovers(): void
    {
        $this->carl = User::where('email', 'magpantayc@nu-lipa.edu.ph')->firstOrFail();
        $this->zaira = User::where('email', 'enayoz@nu-lipa.edu.ph')->firstOrFail();
        $this->assistantDirector = User::where('email', 'quizonpi@nu-lipa.edu.ph')->firstOrFail();
        $this->academicDirector = User::where('email', 'fabitobs@nu-lipa.edu.ph')->firstOrFail();
        $this->executiveDirector = User::where('email', 'palupitad@nu-lipa.edu.ph')->firstOrFail();
    }

    // -------------------------------------------------------------------------
    // Accounts
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function seedAccounts(): array
    {
        $sahs = School::where('name', 'School of Allied Health and Sciences')->where('type', 'regular')->orderBy('id')->firstOrFail();
        $medTech = Program::where('school_id', $sahs->id)->where('name', 'Medical Technology')->firstOrFail();

        // New advisers — beyond the 3 freed by the wipe (Adviser One/Two/SHS)
        // plus Marvin Atanacio (unbound), the org lineup below needs 4 more,
        // plus one spare that's deliberately never bound to a founded org:
        // ApproveOrganizationRegistration re-checks adviser binding on EVERY
        // approval call, not just the completing one, so the "In Review,
        // partial quorum" registration example needs a genuinely unbound
        // adviser — by the time that spread is seeded, all 8 real orgs'
        // advisers are already bound.
        $adviserRoxas = $this->makeStaff('Engr. Michael Roxas', 'roxasm');
        $adviserSantiago = $this->makeStaff('Dr. Fatima Santiago', 'santiagof');
        $adviserDelaCruzR = $this->makeStaff('Ramon Dela Cruz', 'delacruzr');
        $adviserVillanueva = $this->makeStaff('Grace Villanueva', 'villanuevag');
        $adviserSpare = $this->makeStaff('Nora Espiritu', 'espiritun');
        foreach ([$adviserRoxas, $adviserSantiago, $adviserDelaCruzR, $adviserVillanueva, $adviserSpare] as $adviser) {
            RoleAssignment::create(['user_id' => $adviser->id, 'role' => Role::Adviser, 'organization_id' => null]);
        }
        $this->summary['new adviser accounts'] = 5;

        // Medical Technology has no program_chair at all yet — MTSC's chain
        // can't resolve without one.
        $mtChair = $this->makeStaff('Dr. Cristina Bautista', 'bautistac');
        RoleAssignment::create(['user_id' => $mtChair->id, 'role' => Role::ProgramChair, 'program_id' => $medTech->id]);
        $this->summary['new program chair accounts'] = 1;

        $adviserOne = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
        $adviserTwo = User::where('email', 'adviser-two@nu-lipa.edu.ph')->firstOrFail();
        $adviserShs = User::where('email', 'adviser-shs@nu-lipa.edu.ph')->firstOrFail();
        $marvin = User::where('email', 'atanaciom@nu-lipa.edu.ph')->firstOrFail();

        // Presidents — one per founding org (bound automatically once each
        // registration is Approved, see foundOrganizations()).
        $presidents = [
            'CODECS' => $this->makeStudent('Miguel Torres', 'torresm'),
            'UAPSA' => $this->makeStudent('Isabelle Reyes', 'reyesi'),
            'PICE' => $this->makeStudent('Nathaniel Cruz', 'cruzn'),
            'Psychology Org' => $this->makeStudent('Samantha Diaz', 'diazs'),
            'MTSC' => $this->makeStudent('Kevin Mendoza', 'mendozak'),
            'JPIA' => $this->makeStudent('Andrea Villareal', 'villarreala'),
            'Red Cross Youth' => $this->makeStudent('Patricia Gomez', 'gomezp'),
            'Venaris Esports' => $this->makeStudent('Joshua Ramos', 'ramosj'),
        ];
        $this->summary['new student accounts (org presidents)'] = count($presidents);

        // Secretaries — a few orgs, for variety (bound after founding).
        $secretaries = [
            'CODECS' => $this->makeStudent('Bianca Fernandez', 'fernandezb'),
            'PICE' => $this->makeStudent('Christian Aquino', 'aquinoc'),
            'JPIA' => $this->makeStudent('Denise Manalo', 'manalod'),
        ];
        $this->summary['new student accounts (org secretaries)'] = count($secretaries);

        // Registration status-spread — 4 more founding attempts that never
        // reach Approved.
        $regSpreadStudents = [
            'draft' => $this->makeStudent('Ella Marquez', 'marqueze'),
            'in_review_partial' => $this->makeStudent('Francis Ocampo', 'ocampof'),
            'returned' => $this->makeStudent('Grace Lim', 'limg'),
            'rejected' => $this->makeStudent('Harold Navarro', 'navarroh'),
        ];
        $this->summary['new student accounts (registration status spread)'] = count($regSpreadStudents);

        // A couple of verified-but-unaffiliated students, for realism.
        $unaffiliated = [
            $this->makeStudent('Louis Serrano', 'serranol'),
            $this->makeStudent('Katrina Valdez', 'valdezk'),
        ];
        $this->summary['new student accounts (unaffiliated)'] = count($unaffiliated);

        // Pending Accounts queue — fresh Unverified self-registrations, plus
        // one Rejected example.
        $unverified = [
            $this->makeStudent('Rafael Espino', 'espinor', unverified: true),
            $this->makeStudent('Trisha Bonifacio', 'bonifaciot', unverified: true),
            $this->makeStudent('Julian Castro', 'castroj', unverified: true),
        ];
        $rejectedAccount = $this->makeStudent('Vince Aguilar', 'aguilarv', rejected: true);
        $this->summary['new student accounts (unverified, Pending Accounts queue)'] = count($unverified);
        $this->summary['new student accounts (rejected)'] = 1;

        return [
            'advisers' => [
                'CODECS' => $adviserOne,
                'UAPSA' => $adviserTwo,
                'PICE' => $adviserRoxas,
                'Psychology Org' => $adviserSantiago,
                'MTSC' => $adviserDelaCruzR,
                'JPIA' => $marvin,
                'Red Cross Youth' => $adviserVillanueva,
                'Venaris Esports' => $adviserShs,
            ],
            'presidents' => $presidents,
            'secretaries' => $secretaries,
            'regSpreadStudents' => $regSpreadStudents,
            'unaffiliated' => $unaffiliated,
            'unverified' => $unverified,
            'rejectedAccount' => $rejectedAccount,
            'spareAdviser' => $adviserSpare,
        ];
    }

    private function makeStaff(string $name, string $localPart): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => "{$localPart}@nu-lipa.edu.ph",
            'password' => Hash::make(self::PASSWORD),
        ]);
    }

    private function makeStudent(string $name, string $localPart, bool $unverified = false, bool $rejected = false): User
    {
        $factory = User::factory();

        if ($unverified) {
            $factory = $factory->unverifiedAccount();
        } elseif ($rejected) {
            $factory = $factory->rejectedAccount();
        }

        return $factory->create([
            'name' => $name,
            'email' => "{$localPart}@students.nu-lipa.edu.ph",
            'password' => Hash::make(self::PASSWORD),
            'id_number' => $this->nextIdNumber(),
        ]);
    }

    private function nextIdNumber(): string
    {
        $n = $this->idNumberSeq++;

        return '2024-'.str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }

    // -------------------------------------------------------------------------
    // Organizations — real NU Lipa names, founded through the real chain.
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $accounts
     * @return array<string, Organization>
     */
    private function foundOrganizations(array $accounts): array
    {
        $sace = School::where('name', 'School of Architecture, Computing, and Engineering')->orderBy('id')->firstOrFail();
        $sahs = School::where('name', 'School of Allied Health and Sciences')->orderBy('id')->firstOrFail();
        $sabm = School::where('name', 'School of Accountancy, Business, and Management')->orderBy('id')->firstOrFail();
        $shs = School::where('name', 'Senior High School')->where('type', 'senior_high')->orderBy('id')->firstOrFail();

        $programCs = Program::where('school_id', $sace->id)->where('name', 'BS Computer Science')->firstOrFail();
        $programArchitecture = Program::where('school_id', $sace->id)->where('name', 'BS Architecture')->firstOrFail();
        $programCivilEngineering = Program::where('school_id', $sace->id)->where('name', 'BS Civil Engineering')->firstOrFail();
        $programPsychology = Program::where('school_id', $sahs->id)->where('name', 'BS Psychology')->firstOrFail();
        $programMedTech = Program::where('school_id', $sahs->id)->where('name', 'Medical Technology')->firstOrFail();
        $programAccountancy = Program::where('school_id', $sabm->id)->where('name', 'BS Accountancy')->firstOrFail();

        $specs = [
            'CODECS' => [$sace->id, $programCs->id, OrganizationType::CoCurricular, 'the college coders\' and computer science enthusiasts\' organization'],
            'UAPSA' => [$sace->id, $programArchitecture->id, OrganizationType::CoCurricular, 'the United Architects of the Philippines Student Auxiliary chapter'],
            'PICE' => [$sace->id, $programCivilEngineering->id, OrganizationType::CoCurricular, 'the Philippine Institute of Civil Engineers student chapter'],
            'Psychology Org' => [$sahs->id, $programPsychology->id, OrganizationType::CoCurricular, 'the BS Psychology student organization'],
            'MTSC' => [$sahs->id, $programMedTech->id, OrganizationType::CoCurricular, 'the Medical Technology Student Council'],
            'JPIA' => [$sabm->id, $programAccountancy->id, OrganizationType::CoCurricular, 'the Junior Philippine Institute of Accountants chapter'],
            'Red Cross Youth' => [$sahs->id, null, OrganizationType::ExtraCurricular, 'the campus Red Cross Youth volunteer corps'],
            'Venaris Esports' => [$shs->id, null, OrganizationType::ExtraCurricular, 'the Senior High School competitive esports club'],
        ];

        $orgs = [];

        foreach ($specs as $name => [$schoolId, $programId, $type, $purpose]) {
            $president = $accounts['presidents'][$name];
            $adviser = $accounts['advisers'][$name];
            $adviserAssignment = RoleAssignment::where('user_id', $adviser->id)->where('role', Role::Adviser->value)->firstOrFail();

            $document = $this->submitRegistration->execute(
                actor: $president,
                name: $name,
                schoolId: $schoolId,
                programId: $programId,
                adviserId: $adviserAssignment->user_id,
                organizationType: $type,
                purposeOfOrganization: "Founded to serve {$purpose} of NU Lipa.",
                contactPerson: $president->name,
                contactNo: '0917'.random_int(1000000, 9999999),
                emailAddress: $president->email,
                dateOrganized: '2024-08-'.str_pad((string) random_int(1, 28), 2, '0', STR_PAD_LEFT),
                attachmentFiles: $this->fakeAttachmentsFor(FormType::OrganizationRegistration),
            );

            $this->approveRegistration->execute($document, $this->carl);
            $document->refresh();
            $this->approveRegistration->execute($document, $this->zaira);
            $document->refresh();

            $orgs[$name] = Organization::findOrFail($document->organization_id);
        }

        $this->summary['organizations founded (Approved registration)'] = count($orgs);

        return $orgs;
    }

    /**
     * @param  array<string, Organization>  $orgs
     * @param  array<string, mixed>  $accounts
     */
    private function bindSecretaries(array $orgs, array $accounts): void
    {
        foreach ($accounts['secretaries'] as $orgName => $secretary) {
            $adviser = $accounts['advisers'][$orgName];
            $this->bindOfficer->execute($adviser, $orgs[$orgName], $secretary, OfficerPosition::Secretary);
        }

        $this->summary['secretaries bound'] = count($accounts['secretaries']);
    }

    // -------------------------------------------------------------------------
    // Registration — full status spread beyond the 8 Approved foundings.
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $accounts
     */
    private function seedRegistrationStatusSpread(array $accounts): void
    {
        $sabm = School::where('name', 'School of Accountancy, Business, and Management')->orderBy('id')->firstOrFail();
        $sace = School::where('name', 'School of Architecture, Computing, and Engineering')->orderBy('id')->firstOrFail();
        $programMarketing = Program::where('school_id', $sabm->id)->where('name', 'BS Business Administration (Marketing Management)')->firstOrFail();
        $programIt = Program::where('school_id', $sace->id)->where('name', 'BS Information Technology')->firstOrFail();

        $anyAdviser = RoleAssignment::where('role', Role::Adviser->value)->whereNotNull('user_id')->inRandomOrder()->firstOrFail();
        // Deliberately an ALREADY-BOUND adviser (CODECS's), so the Returned
        // example mirrors the real "adviser already assigned elsewhere"
        // scenario from the remediation rules.
        $boundAdviser = RoleAssignment::where('role', Role::Adviser->value)->whereNotNull('organization_id')->firstOrFail();
        // Deliberately UNBOUND — ApproveOrganizationRegistration re-checks
        // adviser availability on every single approval call, not just the
        // completing one, so the partial-quorum example below needs an
        // adviser nothing else has claimed (see seedAccounts()).
        $spareAdviserId = $accounts['spareAdviser']->id;

        // Draft — G17 (sustainability org), hand-built up to the same point
        // SubmitOrganizationRegistration itself would leave it at, since
        // that action has no save-as-draft entry point (see class docblock).
        $this->createDraftRegistrationLikeDocument(
            formType: FormType::OrganizationRegistration,
            actor: $accounts['regSpreadStudents']['draft'],
            orgName: 'G17',
            schoolId: $programIt->school_id,
            programId: $programIt->id,
            adviserId: $anyAdviser->user_id,
            organizationType: OrganizationType::ExtraCurricular,
        );

        // In Review, partial SDAO quorum — NEXUS.
        $nexusDoc = $this->submitRegistration->execute(
            actor: $accounts['regSpreadStudents']['in_review_partial'],
            name: 'NEXUS',
            schoolId: $programIt->school_id,
            programId: $programIt->id,
            adviserId: $spareAdviserId,
            organizationType: OrganizationType::ExtraCurricular,
            purposeOfOrganization: 'A cross-program networking and leadership org for NU Lipa students.',
            contactPerson: $accounts['regSpreadStudents']['in_review_partial']->name,
            contactNo: '0917'.random_int(1000000, 9999999),
            emailAddress: $accounts['regSpreadStudents']['in_review_partial']->email,
            dateOrganized: '2025-01-15',
            attachmentFiles: $this->fakeAttachmentsFor(FormType::OrganizationRegistration),
        );
        $this->approveRegistration->execute($nexusDoc, $this->carl);

        // Returned, flagged sections — COMEX, using an already-bound adviser.
        $comexDoc = $this->submitRegistration->execute(
            actor: $accounts['regSpreadStudents']['returned'],
            name: 'COMEX',
            schoolId: $programMarketing->school_id,
            programId: $programMarketing->id,
            adviserId: $boundAdviser->user_id,
            organizationType: OrganizationType::CoCurricular,
            purposeOfOrganization: 'Community extension and outreach programs for NU Lipa student organizations.',
            contactPerson: $accounts['regSpreadStudents']['returned']->name,
            contactNo: '0917'.random_int(1000000, 9999999),
            emailAddress: $accounts['regSpreadStudents']['returned']->email,
            dateOrganized: '2025-02-10',
            attachmentFiles: $this->fakeAttachmentsFor(FormType::OrganizationRegistration),
        );
        $this->engine->returnForRevision(
            $comexDoc,
            $this->zaira,
            comment: 'The chosen adviser is already assigned to another organization. Please pick a different available adviser.',
            flaggedSections: ['adviser_selection', 'attachments'],
            sectionComments: ['adviser_selection' => 'This adviser is already bound elsewhere — choose someone else.'],
        );

        // Rejected — CREA8ives.
        $crea8ivesDoc = $this->submitRegistration->execute(
            actor: $accounts['regSpreadStudents']['rejected'],
            name: 'CREA8ives',
            schoolId: $programMarketing->school_id,
            programId: $programMarketing->id,
            adviserId: $anyAdviser->user_id,
            organizationType: OrganizationType::ExtraCurricular,
            purposeOfOrganization: 'A creatives and multimedia arts collective.',
            contactPerson: $accounts['regSpreadStudents']['rejected']->name,
            contactNo: '0917'.random_int(1000000, 9999999),
            emailAddress: $accounts['regSpreadStudents']['rejected']->email,
            dateOrganized: '2025-03-01',
            attachmentFiles: $this->fakeAttachmentsFor(FormType::OrganizationRegistration),
        );
        $this->engine->reject($crea8ivesDoc, $this->carl, comment: 'Incomplete and inconsistent supporting documents. Please file a new registration once ready.');

        $this->summary['registration documents (status spread: draft/in-review/returned/rejected)'] = 4;
    }

    // -------------------------------------------------------------------------
    // Renewal — same status spread, across 5 of the 8 founded orgs.
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, Organization>  $orgs
     */
    private function seedRenewalStatusSpread(array $orgs): void
    {
        $renewalFields = fn (Organization $org) => [
            'organizationType' => OrganizationType::CoCurricular,
            'purposeOfOrganization' => "Renewing {$org->name}'s recognition for the current academic year.",
            'contactPerson' => $org->name.' Officers',
            'contactNo' => '0917'.random_int(1000000, 9999999),
            'emailAddress' => strtolower(str_replace(' ', '', $org->name)).'@students.nu-lipa.edu.ph',
            'dateOrganized' => '2024-08-15',
        ];

        // Draft — CODECS, hand-built (no save-as-draft entry point either).
        $codecsPresident = $this->presidentOf($orgs['CODECS']);
        $this->createDraftRegistrationLikeDocument(
            formType: FormType::OrganizationRenewal,
            actor: $codecsPresident,
            orgName: $orgs['CODECS']->name,
            schoolId: $orgs['CODECS']->school_id,
            programId: $orgs['CODECS']->program_id,
            adviserId: $this->currentAdviserOf($orgs['CODECS'])->id,
            organizationType: OrganizationType::CoCurricular,
            organization: $orgs['CODECS'],
        );

        // In Review, partial quorum — UAPSA.
        $uapsaFields = $renewalFields($orgs['UAPSA']);
        $uapsaDoc = $this->submitRenewal->execute(
            actor: $this->presidentOf($orgs['UAPSA']),
            organization: $orgs['UAPSA'],
            organizationType: $uapsaFields['organizationType'],
            purposeOfOrganization: $uapsaFields['purposeOfOrganization'],
            contactPerson: $uapsaFields['contactPerson'],
            contactNo: $uapsaFields['contactNo'],
            emailAddress: $uapsaFields['emailAddress'],
            dateOrganized: $uapsaFields['dateOrganized'],
            attachmentFiles: $this->fakeAttachmentsFor(FormType::OrganizationRenewal),
        );
        $this->engine->approve($uapsaDoc, $this->carl);

        // Returned, flagged sections — PICE.
        $piceFields = $renewalFields($orgs['PICE']);
        $piceDoc = $this->submitRenewal->execute(
            actor: $this->presidentOf($orgs['PICE']),
            organization: $orgs['PICE'],
            organizationType: $piceFields['organizationType'],
            purposeOfOrganization: $piceFields['purposeOfOrganization'],
            contactPerson: $piceFields['contactPerson'],
            contactNo: $piceFields['contactNo'],
            emailAddress: $piceFields['emailAddress'],
            dateOrganized: $piceFields['dateOrganized'],
            attachmentFiles: $this->fakeAttachmentsFor(FormType::OrganizationRenewal),
        );
        $this->engine->returnForRevision(
            $piceDoc,
            $this->carl,
            comment: 'Please update the list of past projects — the current one is missing this year\'s activities.',
            flaggedSections: ['past_projects_list', 'evaluation_summary'],
            sectionComments: null,
        );

        // Approved — JPIA.
        $jpiaFields = $renewalFields($orgs['JPIA']);
        $jpiaDoc = $this->submitRenewal->execute(
            actor: $this->presidentOf($orgs['JPIA']),
            organization: $orgs['JPIA'],
            organizationType: $jpiaFields['organizationType'],
            purposeOfOrganization: $jpiaFields['purposeOfOrganization'],
            contactPerson: $jpiaFields['contactPerson'],
            contactNo: $jpiaFields['contactNo'],
            emailAddress: $jpiaFields['emailAddress'],
            dateOrganized: $jpiaFields['dateOrganized'],
            attachmentFiles: $this->fakeAttachmentsFor(FormType::OrganizationRenewal),
        );
        $this->engine->approve($jpiaDoc, $this->carl);
        $jpiaDoc->refresh();
        $this->engine->approve($jpiaDoc, $this->zaira);

        // Rejected — Red Cross Youth.
        $rcyFields = $renewalFields($orgs['Red Cross Youth']);
        $rcyDoc = $this->submitRenewal->execute(
            actor: $this->presidentOf($orgs['Red Cross Youth']),
            organization: $orgs['Red Cross Youth'],
            organizationType: $rcyFields['organizationType'],
            purposeOfOrganization: $rcyFields['purposeOfOrganization'],
            contactPerson: $rcyFields['contactPerson'],
            contactNo: $rcyFields['contactNo'],
            emailAddress: $rcyFields['emailAddress'],
            dateOrganized: $rcyFields['dateOrganized'],
            attachmentFiles: $this->fakeAttachmentsFor(FormType::OrganizationRenewal),
        );
        $this->engine->reject($rcyDoc, $this->zaira, comment: 'Financial statement does not reconcile with the reported past projects. Please resubmit as a new renewal once corrected.');

        $this->summary['renewal documents (status spread: draft/in-review/returned/approved/rejected)'] = 5;
    }

    // -------------------------------------------------------------------------
    // Activity calendars — one per founded org, 7 Approved + 1 In Review.
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, Organization>  $orgs
     * @return array<string, array<int, CalendarActivity>>
     */
    private function seedActivityCalendars(array $orgs): array
    {
        $specs = [
            'CODECS' => [
                ['name' => 'CS Foundations Orientation', 'venue' => 'Room 201', 'activity_date' => '2026-07-18', 'start_time' => '09:00', 'end_time' => '11:00'],
                ['name' => 'Hackathon Kickoff', 'venue' => 'Function Hall', 'activity_date' => '2026-09-05', 'start_time' => '10:00', 'end_time' => '16:00'],
                ['name' => 'Code Review Bootcamp', 'venue' => 'AVR 1', 'activity_date' => '2026-09-26', 'start_time' => '13:00', 'end_time' => '16:00'],
            ],
            'UAPSA' => [
                ['name' => 'Design Critique Night', 'venue' => 'Room 201', 'activity_date' => '2026-06-20', 'start_time' => '14:00', 'end_time' => '17:00'],
                ['name' => 'Architecture Expo', 'venue' => 'Gymnasium', 'activity_date' => '2026-10-02', 'start_time' => '09:00', 'end_time' => '17:00'],
            ],
            'PICE' => [
                ['name' => 'Structures Symposium', 'venue' => 'AVR 1', 'activity_date' => '2026-07-05', 'start_time' => '13:00', 'end_time' => '16:00'],
                ['name' => 'Bridge Design Challenge', 'venue' => 'Function Hall', 'activity_date' => '2026-10-15', 'start_time' => '08:00', 'end_time' => '17:00'],
            ],
            'Psychology Org' => [
                ['name' => 'Mental Health Awareness Week', 'venue' => 'AVR 1', 'activity_date' => '2026-09-12', 'start_time' => '09:00', 'end_time' => '17:00'],
                ['name' => 'Peer Counseling Training', 'venue' => 'Room 201', 'activity_date' => '2026-10-08', 'start_time' => '13:00', 'end_time' => '16:00'],
            ],
            'MTSC' => [
                ['name' => 'MedTech Lab Safety Seminar', 'venue' => 'Room 201', 'activity_date' => '2026-07-25', 'start_time' => '09:00', 'end_time' => '12:00'],
                ['name' => 'Blood Donation Drive', 'venue' => 'Gymnasium', 'activity_date' => '2026-09-18', 'start_time' => '08:00', 'end_time' => '15:00'],
            ],
            'JPIA' => [
                ['name' => 'Accountancy Career Talk', 'venue' => 'AVR 1', 'activity_date' => '2026-06-28', 'start_time' => '13:00', 'end_time' => '15:00'],
                ['name' => 'Mock CPA Board Exam', 'venue' => 'Function Hall', 'activity_date' => '2026-10-24', 'start_time' => '08:00', 'end_time' => '17:00'],
            ],
            'Red Cross Youth' => [
                ['name' => 'First Aid Training', 'venue' => 'Room 201', 'activity_date' => '2026-07-11', 'start_time' => '09:00', 'end_time' => '16:00'],
                ['name' => 'Community Blood Drive', 'venue' => 'Gymnasium', 'activity_date' => '2026-11-05', 'start_time' => '08:00', 'end_time' => '15:00'],
            ],
            'Venaris Esports' => [
                ['name' => 'Intramurals Gaming Night', 'venue' => 'SHS Gymnasium', 'activity_date' => '2026-07-30', 'start_time' => '17:00', 'end_time' => '21:00'],
                ['name' => 'Valorant Campus Cup', 'venue' => 'SHS Gymnasium', 'activity_date' => '2026-09-19', 'start_time' => '13:00', 'end_time' => '18:00'],
            ],
        ];

        $activitiesByOrg = [];
        $approvedCount = 0;
        $inReviewCount = 0;

        foreach ($specs as $orgName => $activities) {
            $result = $this->submitCalendar->execute(
                actor: $this->presidentOf($orgs[$orgName]),
                organization: $orgs[$orgName],
                activities: $activities,
            );

            $document = $result['document'];

            // Leave Psychology Org's calendar In Review — the "tentative"
            // demonstration case.
            if ($orgName !== 'Psychology Org') {
                $this->engine->approve($document, $this->carl);
                $document->refresh();
                $this->engine->approve($document, $this->zaira);
                $approvedCount++;
            } else {
                $inReviewCount++;
            }

            $document->refresh();
            $activitiesByOrg[$orgName] = $document->activityCalendar->activities()->orderBy('id')->get()->all();
        }

        $this->summary['activity calendars (Approved)'] = $approvedCount;
        $this->summary['activity calendars (In Review)'] = $inReviewCount;
        $this->summary['calendar activities seeded'] = collect($activitiesByOrg)->sum(fn ($a) => count($a));

        return $activitiesByOrg;
    }

    // -------------------------------------------------------------------------
    // Activity proposals — all 4 variants, full status spread.
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, Organization>  $orgs
     * @param  array<string, array<int, CalendarActivity>>  $calendarActivities
     * @return array<string, ActivityProposal> keyed by a short label used by seedAfterActivityReports()
     */
    private function seedActivityProposals(array $orgs, array $calendarActivities): array
    {
        $proposals = [];
        $statusCounts = ['draft' => 0, 'in_review' => 0, 'returned' => 0, 'approved' => 0, 'rejected' => 0];

        // 1. Draft — CODECS, on-calendar (regular_on_calendar), step 1 only.
        $codecsPresident = $this->presidentOf($orgs['CODECS']);
        $draftDoc = $this->startProposalDraft->execute(
            actor: $codecsPresident,
            organization: $orgs['CODECS'],
            mode: ProposalCalendarMode::OnCalendar,
            data: [
                'calendar_activity_id' => $calendarActivities['CODECS'][1]->id, // Hackathon Kickoff
                'activity_nature' => 'co_curricular',
                'activity_type' => 'seminar_workshop',
                'proposed_budget' => 15000,
                'budget_source' => 'Organization funds',
            ],
        );
        $statusCounts['draft']++;

        // 2. In Review, partial SDAO quorum — PICE, off-calendar (regular_off_calendar).
        $picePresident = $this->presidentOf($orgs['PICE']);
        $piceOffDoc = $this->startProposalDraft->execute(
            actor: $picePresident,
            organization: $orgs['PICE'],
            mode: ProposalCalendarMode::OffCalendar,
            data: [
                'title' => 'Civil Engineering Site Visit',
                'venue' => 'Site Visit - Batangas',
                'activity_date' => '2026-10-05',
                'start_time' => '08:00',
                'end_time' => '17:00',
                'activity_nature' => 'co_curricular',
                'activity_type' => 'off_campus_activity',
                'proposed_budget' => 8000,
                'budget_source' => 'Organization funds',
            ],
        );
        $result = $this->submitProposal->execute(
            actor: $picePresident,
            document: $piceOffDoc,
            objectives: 'Expose members to real-world structural engineering practices.',
            narrative: 'A guided site visit to an active construction project in Batangas, with a Q&A session with the site engineers.',
        );
        $piceOffDoc = $result['document'];
        $this->engine->approve($piceOffDoc, $this->carl);
        $statusCounts['in_review']++;

        // 3. Returned, flagged sections — UAPSA, on-calendar (regular_on_calendar).
        $uapsaPresident = $this->presidentOf($orgs['UAPSA']);
        $uapsaDraftDoc = $this->startProposalDraft->execute(
            actor: $uapsaPresident,
            organization: $orgs['UAPSA'],
            mode: ProposalCalendarMode::OnCalendar,
            data: [
                'calendar_activity_id' => $calendarActivities['UAPSA'][1]->id, // Architecture Expo
                'activity_nature' => 'co_curricular',
                'activity_type' => 'competition',
                'proposed_budget' => 25000,
                'budget_source' => 'Organization funds and sponsorships',
            ],
        );
        $result = $this->submitProposal->execute(
            actor: $uapsaPresident,
            document: $uapsaDraftDoc,
            objectives: 'Showcase student architectural design work to the NU Lipa community.',
            narrative: 'A campus-wide exhibit of student architectural models and boards, open to all schools.',
            sourceOfFunding: 'Organization funds and sponsorships',
        );
        $uapsaProposalDoc = $result['document'];
        $adviserUapsa = $this->currentAdviserOf($orgs['UAPSA']);
        $this->engine->approve($uapsaProposalDoc, $adviserUapsa);
        $uapsaProposalDoc->refresh();
        $chairArchitecture = RoleAssignment::where('role', Role::ProgramChair->value)->where('program_id', $orgs['UAPSA']->program_id)->firstOrFail()->user;
        $this->engine->returnForRevision(
            $uapsaProposalDoc,
            $chairArchitecture,
            comment: 'Please itemize the proposed budget in more detail before this can proceed to the Dean.',
            flaggedSections: ['budget', 'schedule_venue'],
        );
        $statusCounts['returned']++;

        // 4. Approved — Venaris Esports, on-calendar (shs_on_calendar), full chain.
        $venarisPresident = $this->presidentOf($orgs['Venaris Esports']);
        $venarisOnDoc = $this->startProposalDraft->execute(
            actor: $venarisPresident,
            organization: $orgs['Venaris Esports'],
            mode: ProposalCalendarMode::OnCalendar,
            data: [
                'calendar_activity_id' => $calendarActivities['Venaris Esports'][1]->id, // Valorant Campus Cup
                'activity_nature' => 'non_curricular',
                'activity_type' => 'competition',
                'proposed_budget' => 12000,
                'budget_source' => 'Organization funds',
            ],
        );
        $result = $this->submitProposal->execute(
            actor: $venarisPresident,
            document: $venarisOnDoc,
            objectives: 'Promote esports as a legitimate co-curricular activity among SHS students.',
            narrative: 'A campus-wide Valorant tournament open to all SHS student teams, with a small prize pool.',
            programFlow: 'Opening remarks, group stage, playoffs, awarding.',
        );
        $venarisApprovedDoc = $this->approveEntireChain($result['document'], $orgs['Venaris Esports']);
        $proposals['venaris_approved'] = $venarisApprovedDoc->activityProposal;
        $statusCounts['approved']++;

        // 5. Rejected — Venaris Esports, off-calendar (shs_off_calendar).
        $venarisOffDoc = $this->startProposalDraft->execute(
            actor: $venarisPresident,
            organization: $orgs['Venaris Esports'],
            mode: ProposalCalendarMode::OffCalendar,
            data: [
                'title' => 'SHS Talent Night',
                'venue' => 'SHS Function Room',
                'activity_date' => '2026-10-30',
                'start_time' => '13:00',
                'end_time' => '17:00',
                'activity_nature' => 'non_curricular',
                'activity_type' => 'others',
                'proposed_budget' => 6000,
                'budget_source' => 'Organization funds',
            ],
        );
        $result = $this->submitProposal->execute(
            actor: $venarisPresident,
            document: $venarisOffDoc,
            objectives: 'Showcase SHS student talent outside of esports.',
            narrative: 'An open-mic style talent night for SHS students, off the approved activity calendar.',
        );
        $venarisOffDoc = $result['document'];
        $this->engine->reject($venarisOffDoc, $this->zaira, comment: 'Duplicates an already-approved event on the calendar this term. Please coordinate timing with SDAO before resubmitting.');
        $statusCounts['rejected']++;

        // 6. Approved (report-feeder) — CODECS, on-calendar (regular_on_calendar).
        $codecsOnDoc = $this->startProposalDraft->execute(
            actor: $codecsPresident,
            organization: $orgs['CODECS'],
            mode: ProposalCalendarMode::OnCalendar,
            data: [
                'calendar_activity_id' => $calendarActivities['CODECS'][2]->id, // Code Review Bootcamp
                'activity_nature' => 'co_curricular',
                'activity_type' => 'seminar_workshop',
                'proposed_budget' => 5000,
                'budget_source' => 'Organization funds',
            ],
        );
        $result = $this->submitProposal->execute(
            actor: $codecsPresident,
            document: $codecsOnDoc,
            objectives: 'Improve code quality practices among CODECS members.',
            narrative: 'A hands-on bootcamp covering code review etiquette and static analysis tooling.',
        );
        $codecsApprovedDoc = $this->approveEntireChain($result['document'], $orgs['CODECS']);
        $proposals['codecs_approved'] = $codecsApprovedDoc->activityProposal;
        $statusCounts['approved']++;

        // 7. Approved (report-feeder) — PICE, off-calendar (regular_off_calendar).
        $piceOffDoc2 = $this->startProposalDraft->execute(
            actor: $picePresident,
            organization: $orgs['PICE'],
            mode: ProposalCalendarMode::OffCalendar,
            data: [
                'title' => 'Infrastructure Career Fair',
                'venue' => 'Function Hall Annex',
                'activity_date' => '2026-11-02',
                'start_time' => '09:00',
                'end_time' => '16:00',
                'activity_nature' => 'co_curricular',
                'activity_type' => 'recruitment_audition',
                'proposed_budget' => 10000,
                'budget_source' => 'Organization funds and partner sponsorships',
            ],
        );
        $result = $this->submitProposal->execute(
            actor: $picePresident,
            document: $piceOffDoc2,
            objectives: 'Connect graduating civil engineering students with local firms.',
            narrative: 'A career fair featuring construction and engineering firms operating in Batangas.',
        );
        $piceApprovedDoc = $this->approveEntireChain($result['document'], $orgs['PICE']);
        $proposals['pice_approved'] = $piceApprovedDoc->activityProposal;
        $statusCounts['approved']++;

        foreach ($statusCounts as $status => $count) {
            $this->summary["activity proposals ({$status})"] = $count;
        }

        return $proposals;
    }

    /**
     * Walks a Draft/InReview document all the way to Approved, resolving the
     * correct approver at every step from the org's own structure (mirrors
     * StepApproverResolver, except SDAO always uses the two real named
     * members — see class docblock).
     */
    private function approveEntireChain(Document $document, Organization $organization): Document
    {
        $document->refresh();

        while ($document->status === DocumentStatus::InReview) {
            $step = WorkflowStep::query()
                ->where('workflow_template_id', $document->workflow_template_id)
                ->where('position', $document->current_step_position)
                ->firstOrFail();

            foreach ($this->approversForRole($step->role, $organization) as $approver) {
                $this->engine->approve($document, $approver);
                $document->refresh();

                if ($document->status !== DocumentStatus::InReview) {
                    break;
                }
            }
        }

        return $document;
    }

    /**
     * @return array<int, User>
     */
    private function approversForRole(Role $role, Organization $organization): array
    {
        return match ($role) {
            Role::SdaoMember => [$this->carl, $this->zaira],
            Role::Adviser => [$this->currentAdviserOf($organization)],
            Role::ProgramChair => [RoleAssignment::where('role', Role::ProgramChair->value)->where('program_id', $organization->program_id)->firstOrFail()->user],
            Role::Dean => [RoleAssignment::where('role', Role::Dean->value)->where('school_id', $organization->school_id)->firstOrFail()->user],
            Role::Principal => [RoleAssignment::where('role', Role::Principal->value)->where('school_id', $organization->school_id)->firstOrFail()->user],
            Role::AssistantDirectorAcademicServices => [$this->assistantDirector],
            Role::AcademicDirector => [$this->academicDirector],
            Role::ExecutiveDirector => [$this->executiveDirector],
            Role::Student => [],
        };
    }

    // -------------------------------------------------------------------------
    // After-Activity Reports — hard-linked to Approved proposals.
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, ActivityProposal>  $proposals
     */
    private function seedAfterActivityReports(array $proposals): void
    {
        $statusCounts = ['in_review' => 0, 'approved' => 0, 'rejected' => 0];

        // Approved — Venaris Esports.
        $venarisProposal = $proposals['venaris_approved'];
        $venarisOrg = $venarisProposal->document->organization;
        $venarisPresident = $this->presidentOf($venarisOrg);
        $venarisReportDoc = $this->submitReport->execute(
            actor: $venarisPresident,
            proposal: $venarisProposal,
            summary: 'The Valorant Campus Cup drew strong participation across SHS sections and ran without incident.',
            outcomes: 'Increased visibility for esports as a recognized co-curricular activity.',
            participantCount: 96,
            activityChairs: [$venarisPresident->name],
            preparedBy: $venarisPresident->name,
            eventProgram: 'Group stage, playoffs, awarding ceremony.',
            targetParticipantsPercentage: 90,
            attachmentFiles: $this->fakeAttachmentsFor(FormType::AfterActivityReport),
        );
        $this->approveEntireChain($venarisReportDoc, $venarisOrg);
        $statusCounts['approved']++;

        // In Review, partial quorum — CODECS.
        $codecsProposal = $proposals['codecs_approved'];
        $codecsOrg = $codecsProposal->document->organization;
        $codecsPresident = $this->presidentOf($codecsOrg);
        $codecsReportDoc = $this->submitReport->execute(
            actor: $codecsPresident,
            proposal: $codecsProposal,
            summary: 'The Code Review Bootcamp covered static analysis tooling and pairwise review exercises.',
            outcomes: 'Members reported more confidence giving structured code review feedback.',
            participantCount: 38,
            activityChairs: [$codecsPresident->name],
            preparedBy: $codecsPresident->name,
            eventProgram: 'Lecture, hands-on exercise, wrap-up discussion.',
            targetParticipantsPercentage: 80,
            attachmentFiles: $this->fakeAttachmentsFor(FormType::AfterActivityReport),
        );
        $this->engine->approve($codecsReportDoc, $this->carl);
        $statusCounts['in_review']++;

        // Rejected — PICE.
        $piceProposal = $proposals['pice_approved'];
        $piceOrg = $piceProposal->document->organization;
        $picePresident = $this->presidentOf($piceOrg);
        $piceReportDoc = $this->submitReport->execute(
            actor: $picePresident,
            proposal: $piceProposal,
            summary: 'The Infrastructure Career Fair connected students with several partner firms.',
            outcomes: 'Several members received on-the-spot interview invitations.',
            participantCount: 60,
            activityChairs: [$picePresident->name],
            preparedBy: $picePresident->name,
            eventProgram: 'Booth exhibits, employer talks, open networking.',
            targetParticipantsPercentage: 75,
            attachmentFiles: $this->fakeAttachmentsFor(FormType::AfterActivityReport),
        );
        $this->engine->reject($piceReportDoc, $this->zaira, comment: 'Attendance sheet does not match the reported participant count. Please refile with corrected figures.');
        $statusCounts['rejected']++;

        foreach ($statusCounts as $status => $count) {
            $this->summary["after-activity reports ({$status})"] = $count;
        }
    }

    // -------------------------------------------------------------------------
    // Notifications — mark a believable subset read.
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $accounts
     * @param  array<string, Organization>  $orgs
     */
    private function markSomeNotificationsRead(array $accounts, array $orgs): void
    {
        $usersToPartiallyRead = [
            $this->carl,
            $this->assistantDirector,
            $this->currentAdviserOf($orgs['CODECS']),
            $this->presidentOf($orgs['UAPSA']),
            $this->presidentOf($orgs['Venaris Esports']),
        ];

        $marked = 0;

        foreach ($usersToPartiallyRead as $user) {
            $ids = $user->notifications()->orderBy('created_at')->pluck('id');
            $halfCount = intdiv($ids->count(), 2);

            if ($halfCount > 0) {
                $user->notifications()->whereIn('id', $ids->take($halfCount))->update(['read_at' => now()]);
                $marked += $halfCount;
            }
        }

        $this->summary['bell notifications marked read'] = $marked;
        $this->summary['bell notifications total'] = DB::table('notifications')->count();
    }

    // -------------------------------------------------------------------------
    // Shared helpers
    // -------------------------------------------------------------------------

    private function presidentOf(Organization $organization): User
    {
        return OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('position', OfficerPosition::President->value)
            ->where('is_active', true)
            ->firstOrFail()
            ->user;
    }

    private function currentAdviserOf(Organization $organization): User
    {
        return RoleAssignment::query()
            ->where('role', Role::Adviser->value)
            ->where('organization_id', $organization->id)
            ->firstOrFail()
            ->user;
    }

    /**
     * @return array<string, UploadedFile|array<int, UploadedFile>>
     */
    private function fakeAttachmentsFor(FormType $formType): array
    {
        $files = [];

        foreach (AttachmentSlots::for($formType) as $slot) {
            if (! $slot->required) {
                continue;
            }

            if ($slot->multiple) {
                // ->create() rather than ->image() — no real image is ever
                // rendered (this environment has no GD extension) and
                // AttachmentStorage never validates mime type itself, only
                // FormRequest does (an HTTP-layer concern this seeder never
                // goes through), so a fake .jpg-named file is sufficient.
                $files[$slot->key] = [
                    UploadedFile::fake()->create("{$slot->key}-1.jpg", 200, 'image/jpeg'),
                    UploadedFile::fake()->create("{$slot->key}-2.jpg", 200, 'image/jpeg'),
                ];

                continue;
            }

            $files[$slot->key] = UploadedFile::fake()->create("{$slot->key}.pdf", 200, 'application/pdf');
        }

        return $files;
    }

    /**
     * Replicates SubmitOrganizationRegistration's/SubmitOrganizationRenewal's
     * own pre-submit code shape (Organization + Document +
     * OrganizationRegistrationDetail), stopping before engine->submit() —
     * see class docblock for why this is the only way to get a genuine Draft
     * row for these two form types.
     */
    private function createDraftRegistrationLikeDocument(
        FormType $formType,
        User $actor,
        string $orgName,
        int $schoolId,
        ?int $programId,
        int $adviserId,
        OrganizationType $organizationType,
        ?Organization $organization = null,
    ): Document {
        $organization ??= Organization::create([
            'name' => $orgName,
            'school_id' => $schoolId,
            'program_id' => $programId,
        ]);

        $academicYear = AcademicYear::current();

        $document = Document::create([
            'form_type' => $formType,
            'variant' => null,
            'title' => ($formType === FormType::OrganizationRenewal ? 'Organization Renewal — ' : 'Organization Registration — ')."{$organization->name} ({$academicYear})",
            'status' => DocumentStatus::Draft,
            'current_step_position' => null,
            'organization_id' => $organization->id,
            'workflow_template_id' => null,
            'submitted_by' => $actor->id,
        ]);

        OrganizationRegistrationDetail::create([
            'document_id' => $document->id,
            'organization_type' => $organizationType->value,
            'purpose_of_organization' => "Draft {$formType->value} for {$organization->name}, not yet submitted.",
            'contact_person' => $actor->name,
            'contact_no' => '0917'.random_int(1000000, 9999999),
            'email_address' => $actor->email,
            'date_organized' => '2024-08-15',
            'adviser_id' => $adviserId,
            'academic_year' => $formType === FormType::OrganizationRenewal ? $academicYear : null,
        ]);

        return $document;
    }
}
