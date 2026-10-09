<?php

use App\ActivityProposals\ProposalReference;
use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Approval\Exceptions\NoApproverForStepException;
use App\Approval\StepApproverResolver;
use App\Approval\WorkflowTemplateResolver;
use App\Calendar\SubmitActivityCalendar;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Enums\ProposalCalendarMode;
use App\Enums\ProposalVariant;
use App\Enums\Role;
use App\Enums\Term;
use App\Models\ActivityCalendar;
use App\Models\ActivityProposal;
use App\Models\ApprovalNotification;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentStepApproval;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\Program;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Support\AcademicPeriod;
use App\Support\AcademicYear;
use App\Support\CurrentPeriod;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/*
 * Every way a document can enter a step with nobody in post — submit, resubmit,
 * and an approval that would move it on — is refused by StepApproverGuard
 * before anything is saved, for all five form types and every chain variant.
 */

beforeEach(function () {
    Notification::fake();
    Storage::fake(config('filesystems.attachments'));

    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);

    $this->engine = app(ApprovalEngine::class);
    $this->resolver = app(StepApproverResolver::class);
    $this->student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
});

/** @return array<string, int> */
function missingApproverSnapshot(): array
{
    return [
        'documents' => Document::count(),
        'transitions' => DocumentTransition::count(),
        'step_approvals' => DocumentStepApproval::count(),
        'approval_notifications' => ApprovalNotification::count(),
        'attachments' => DocumentAttachment::count(),
        'notifications' => DB::table('notifications')->count(),
        'organizations' => Organization::count(),
    ];
}

function deactivateAll(Collection $users): void
{
    $users->each(fn (User $u) => $u->forceFill(['deactivated_at' => now()])->save());
}

/** @return Collection<int, WorkflowStep> */
function chainSteps(FormType $form, ?ProposalVariant $variant): Collection
{
    return app(WorkflowTemplateResolver::class)->resolve($form, $variant)->steps->sortBy('position')->values();
}

function draftDocument(FormType $form, ?ProposalVariant $variant, Organization $org): Document
{
    return Document::factory()->create([
        'form_type' => $form,
        'variant' => $variant,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Draft,
    ]);
}

/**
 * Approve every step before $position with its real approvers.
 */
function advanceTo(ApprovalEngine $engine, StepApproverResolver $resolver, Document $document, int $position): void
{
    $steps = chainSteps($document->form_type, $document->variant);

    foreach ($steps->where('position', '<', $position) as $step) {
        foreach ($resolver->approversFor($step, $document)->take($step->required_approvals) as $approver) {
            $engine->approve($document, $approver);
            $document->refresh();
        }
    }
}

/**
 * Every (form type, chain variant, organization, step position) the guard has
 * to cover. Short chains have one step; the proposal variants differ by school
 * structure.
 *
 * @return array<string, array{FormType, ?ProposalVariant, string, int}>
 */
function chainStepCases(bool $fromSecondStep): array
{
    $chains = [
        'registration' => [FormType::OrganizationRegistration, null, 'Computing Society'],
        'renewal' => [FormType::OrganizationRenewal, null, 'Computing Society'],
        'activity calendar' => [FormType::ActivityCalendar, null, 'Computing Society'],
        'after-activity report' => [FormType::AfterActivityReport, null, 'Computing Society'],
        'proposal, regular school, on-calendar' => [FormType::ActivityProposal, ProposalVariant::RegularOnCalendar, 'Computing Society'],
        'proposal, regular school, off-calendar' => [FormType::ActivityProposal, ProposalVariant::RegularOffCalendar, 'Computing Society'],
        'proposal, senior high, on-calendar' => [FormType::ActivityProposal, ProposalVariant::ShsOnCalendar, 'SHS Student Council'],
        'proposal, senior high, off-calendar' => [FormType::ActivityProposal, ProposalVariant::ShsOffCalendar, 'SHS Student Council'],
        'proposal, extra-curricular, on-calendar' => [FormType::ActivityProposal, ProposalVariant::ExtraCurricularOnCalendar, 'University Chess Club'],
        'proposal, extra-curricular, off-calendar' => [FormType::ActivityProposal, ProposalVariant::ExtraCurricularOffCalendar, 'University Chess Club'],
    ];
    $stepCounts = [
        'registration' => 1, 'renewal' => 1, 'activity calendar' => 1, 'after-activity report' => 2,
        'proposal, regular school, on-calendar' => 7, 'proposal, regular school, off-calendar' => 7,
        'proposal, senior high, on-calendar' => 6, 'proposal, senior high, off-calendar' => 6,
        'proposal, extra-curricular, on-calendar' => 5, 'proposal, extra-curricular, off-calendar' => 5,
    ];

    $cases = [];

    foreach ($chains as $name => [$form, $variant, $orgName]) {
        $first = $fromSecondStep ? 2 : 1;

        foreach ($stepCounts[$name] >= $first ? range($first, $stepCounts[$name]) : [] as $position) {
            $cases["{$name}, step {$position}"] = [$form, $variant, $orgName, $position];
        }
    }

    return $cases;
}

// ── Submit ───────────────────────────────────────────────────────────────────

test('a submit is refused when the first step has no active holder, and nothing is saved', function (FormType $form, ?ProposalVariant $variant, string $orgName) {
    $org = Organization::where('name', $orgName)->firstOrFail();
    $first = chainSteps($form, $variant)->first();
    deactivateAll($this->resolver->approversForOrganization($first, $org));

    $document = draftDocument($form, $variant, $org);
    $before = missingApproverSnapshot();

    try {
        $this->engine->submit($document, $this->student);
        $this->fail('The submit should have been refused.');
    } catch (NoApproverForStepException $e) {
        expect($e->role)->toBe($first->role)
            ->and($e->getMessage())->toContain('Please contact SDAO');
    }

    expect(missingApproverSnapshot())->toBe($before)
        ->and($document->fresh()->status)->toBe(DocumentStatus::Draft)
        ->and($document->fresh()->workflow_template_id)->toBeNull()
        ->and($document->fresh()->current_step_position)->toBeNull();
})->with(function () {
    return collect(chainStepCases(false))
        ->filter(fn (array $case, string $name) => str_ends_with($name, 'step 1'))
        ->map(fn (array $case) => array_slice($case, 0, 3))
        ->all();
});

test('the refusal names the missing role in plain words', function (Role $role, string $expected) {
    expect(NoApproverForStepException::forSubmitter($role)->getMessage())->toBe($expected);
})->with([
    'adviser' => [Role::Adviser, 'Your organization has no active adviser. Please contact SDAO.'],
    'program chair' => [Role::ProgramChair, 'Your organization’s program has no active program chair. Please contact SDAO.'],
    'dean' => [Role::Dean, 'Your organization’s school has no active dean. Please contact SDAO.'],
    'principal' => [Role::Principal, 'Your organization’s school has no active principal. Please contact SDAO.'],
    'academic director' => [Role::AcademicDirector, 'There is no active Academic Director to review this. Please contact SDAO.'],
]);

test('fewer active SDAO members than the step needs also refuses a submit', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $sdao = $this->resolver->approversForOrganization(chainSteps(FormType::ActivityCalendar, null)->first(), $org);
    deactivateAll($sdao->skip(1));

    $document = draftDocument(FormType::ActivityCalendar, null, $org);

    expect(fn () => $this->engine->submit($document, $this->student))->toThrow(NoApproverForStepException::class);
});

// ── Approving into an empty step ─────────────────────────────────────────────

test('an approval that would move the document into a step with no active holder is refused, and nothing is saved', function (FormType $form, ?ProposalVariant $variant, string $orgName, int $missingPosition) {
    $org = Organization::where('name', $orgName)->firstOrFail();
    $steps = chainSteps($form, $variant);
    $missing = $steps->firstWhere('position', $missingPosition);
    $current = $steps->firstWhere('position', $missingPosition - 1);

    $document = draftDocument($form, $variant, $org);
    $this->engine->submit($document, $this->student);
    $document->refresh();
    advanceTo($this->engine, $this->resolver, $document, $current->position);

    deactivateAll($this->resolver->approversForOrganization($missing, $org));

    // All but the approval that would complete the current step go through.
    $approvers = $this->resolver->approversForOrganization($current, $org)->values();
    foreach ($approvers->take($current->required_approvals - 1) as $approver) {
        $this->engine->approve($document, $approver);
    }
    $document->refresh();
    $before = missingApproverSnapshot();

    $completing = $approvers->get($current->required_approvals - 1);

    try {
        $this->engine->approve($document, $completing);
        $this->fail('The approval should have been refused.');
    } catch (NoApproverForStepException $e) {
        expect($e->role)->toBe($missing->role)
            ->and($e->getMessage())->toContain('SDAO needs to assign one');
    }

    expect(missingApproverSnapshot())->toBe($before)
        ->and($document->fresh()->current_step_position)->toBe($current->position)
        ->and($document->fresh()->status)->toBe(DocumentStatus::InReview);
})->with(fn () => chainStepCases(true));

test('the first of two SDAO approvals is not refused when the step after is empty, since it moves nothing', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $steps = chainSteps(FormType::ActivityProposal, ProposalVariant::RegularOnCalendar);

    $document = draftDocument(FormType::ActivityProposal, ProposalVariant::RegularOnCalendar, $org);
    $this->engine->submit($document, $this->student);
    $document->refresh();
    advanceTo($this->engine, $this->resolver, $document, 4);

    deactivateAll($this->resolver->approversForOrganization($steps->firstWhere('position', 5), $org));

    $sdao = $this->resolver->approversForOrganization($steps->firstWhere('position', 4), $org)->values();
    $this->engine->approve($document, $sdao[0]);

    expect($document->fresh()->current_step_position)->toBe(4)
        ->and(DocumentStepApproval::where('document_id', $document->id)->where('step_position', 4)->count())->toBe(1);
});

// ── Resubmit ─────────────────────────────────────────────────────────────────

test('a resubmit is refused when the step that returned it has no active holder, and nothing is saved', function (FormType $form, ?ProposalVariant $variant, string $orgName, int $returnPosition) {
    $org = Organization::where('name', $orgName)->firstOrFail();
    $step = chainSteps($form, $variant)->firstWhere('position', $returnPosition);

    $document = draftDocument($form, $variant, $org);
    $this->engine->submit($document, $this->student);
    $document->refresh();
    advanceTo($this->engine, $this->resolver, $document, $returnPosition);

    $holders = $this->resolver->approversForOrganization($step, $org);
    $this->engine->returnForRevision($document, $holders->first(), 'Please revise.');
    $document->refresh();

    deactivateAll($holders);
    $before = missingApproverSnapshot();

    try {
        $this->engine->resubmit($document, $this->student);
        $this->fail('The resubmit should have been refused.');
    } catch (NoApproverForStepException $e) {
        expect($e->role)->toBe($step->role)
            ->and($e->getMessage())->toContain('Please contact SDAO');
    }

    expect(missingApproverSnapshot())->toBe($before)
        ->and($document->fresh()->status)->toBe(DocumentStatus::Returned)
        ->and($document->fresh()->current_step_position)->toBe($returnPosition);
})->with(fn () => chainStepCases(false));

// ── The real entry points: nothing saved, a clear message, never a 404 ──────

test('the report form refuses a submit with no active adviser and writes nothing', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $proposal = missingApproverApprovedProposal($this);

    User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail()->forceFill(['deactivated_at' => now()])->save();
    $before = missingApproverSnapshot();
    $filesBefore = Storage::disk(config('filesystems.attachments'))->allFiles();

    $response = $this->actingAs($this->student)->post(route('reports.store'), [
        'activity_proposal_id' => $proposal->id,
        'summary' => 'No adviser to receive it.',
        'activity_chairs' => ['Chair One'],
        'prepared_by' => 'Preparer',
        'event_program' => 'Program.',
        'target_participants_percentage' => 80,
        'attachments' => reportAttachmentFiles(),
    ]);

    $response->assertRedirect();
    expect(session('flash.type'))->toBe('error')
        ->and(session('flash.message'))->toBe('Your organization has no active adviser. Please contact SDAO.')
        ->and(missingApproverSnapshot())->toBe($before)
        ->and(Storage::disk(config('filesystems.attachments'))->allFiles())->toBe($filesBefore);
});

test('the renewal form refuses a submit with no active adviser and writes nothing', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    missingApproverPriorRegistration($org, $this->student);

    User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail()->forceFill(['deactivated_at' => now()])->save();
    $before = missingApproverSnapshot();

    $response = $this->actingAs($this->student)->post(route('renewals.store'), array_merge(
        [
            'organization_type' => 'co_curricular',
            'purpose_of_organization' => 'Renewed description.',
            'contact_person' => 'Renewed Contact',
            'contact_no' => '09172222222',
            'email_address' => 'renewed@example.test',
            'date_organized' => '2020-06-01',
        ],
        ['attachments' => renewalAttachmentFiles()],
    ));

    $response->assertRedirect();
    expect(session('flash.type'))->toBe('error')
        ->and(session('flash.message'))->toBe('Your organization has no active adviser. Please contact SDAO.')
        ->and(missingApproverSnapshot())->toBe($before)
        ->and(Storage::disk(config('filesystems.attachments'))->allFiles())->toBe([]);
});

test('the registration form refuses a submit when SDAO has nobody in post and writes nothing', function () {
    $founder = User::factory()->create();
    $adviser = User::factory()->create();
    RoleAssignment::create(['user_id' => $adviser->id, 'role' => Role::Adviser->value]);
    deactivateAll(User::query()->whereHas('roleAssignments', fn ($q) => $q->where('role', Role::SdaoMember))->get());
    $before = missingApproverSnapshot();

    $school = School::where('name', 'School of Architecture, Computing, and Engineering')->firstOrFail();

    $response = $this->actingAs($founder)->post(route('registrations.store'), array_merge(
        [
            'name' => 'Missing Approver Test Org',
            'organization_type' => 'co_curricular',
            'purpose_of_organization' => 'Testing a missing approver.',
            'contact_person' => 'Contact Person',
            'contact_no' => '09170000000',
            'email_address' => 'contact@example.test',
            'date_organized' => '2020-06-01',
            'school_id' => $school->id,
            'program_id' => Program::where('name', 'BS Computer Science')->value('id'),
            'adviser_id' => $adviser->id,
        ],
        ['attachments' => registrationAttachmentFiles()],
    ));

    $response->assertRedirect();
    expect(session('flash.type'))->toBe('error')
        ->and(session('flash.message'))->toContain('SDAO has no active members')
        ->and(missingApproverSnapshot())->toBe($before)
        ->and(Storage::disk(config('filesystems.attachments'))->allFiles())->toBe([]);
});

test('the calendar form refuses a submit when SDAO has nobody in post and writes nothing', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    deactivateAll(User::query()->whereHas('roleAssignments', fn ($q) => $q->where('role', Role::SdaoMember))->get());
    $before = missingApproverSnapshot();

    expect(fn () => app(SubmitActivityCalendar::class)->execute(
        actor: $this->student,
        organization: $org,
        activities: [[
            'name' => 'Test Event',
            'venue' => 'Gymnasium',
            'activity_date' => '2026-09-15',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]],
    ))->toThrow(NoApproverForStepException::class);

    expect(missingApproverSnapshot())->toBe($before)
        ->and(ActivityCalendar::count())->toBe(0);
});

test('the proposal form refuses step 2 with no active adviser, and the draft stays a draft', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $activity = missingApproverApprovedActivity($org);

    $draft = app(StartProposalDraft::class)->execute(
        actor: $this->student,
        organization: $org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail()->forceFill(['deactivated_at' => now()])->save();
    $before = missingApproverSnapshot();

    $response = $this->actingAs($this->student)->post(route('activity-proposals.submit', $draft), [
        'objectives' => "Overall Goal\n\nSpecific Objectives",
        'activity_description' => 'Description.',
        'criteria_mechanics' => 'Mechanics.',
        'program_flow' => 'Flow.',
        'expense_items' => [['material' => 'Chairs', 'quantity' => 2, 'unit_price' => 10]],
        'responsible_persons' => ['Person One'],
    ]);

    $response->assertRedirect();
    expect(session('flash.type'))->toBe('error')
        ->and(session('flash.message'))->toBe('Your organization has no active adviser. Please contact SDAO.')
        ->and(missingApproverSnapshot())->toBe($before)
        ->and($draft->fresh()->status)->toBe(DocumentStatus::Draft);
});

test('an approver who would move a proposal into an empty step gets a toast naming the role, and nothing changes', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $chair = User::where('email', 'chair-cs@nu-lipa.edu.ph')->firstOrFail();

    $document = draftDocument(FormType::ActivityProposal, ProposalVariant::RegularOnCalendar, $org);
    $this->engine->submit($document, $this->student);
    $chair->forceFill(['deactivated_at' => now()])->save();
    $before = missingApproverSnapshot();

    $response = $this->actingAs($adviser)->withoutVite()->post(route('review.activity-proposals.approve', $document));

    $response->assertRedirect();
    expect(session('flash.type'))->toBe('error')
        ->and(session('flash.title'))->toBe('Can’t approve yet')
        ->and(session('flash.message'))->toContain('program chair')
        ->and(session('flash.message'))->toContain('SDAO needs to assign one')
        ->and(missingApproverSnapshot())->toBe($before)
        ->and($document->fresh()->current_step_position)->toBe(1);
});

test('an adviser approving a report into an empty SDAO step gets the same toast', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    $document = draftDocument(FormType::AfterActivityReport, null, $org);
    $this->engine->submit($document, $this->student);
    deactivateAll(User::query()->whereHas('roleAssignments', fn ($q) => $q->where('role', Role::SdaoMember))->get());
    $before = missingApproverSnapshot();

    $this->actingAs($adviser)->withoutVite()->post(route('review.reports.approve', $document))->assertRedirect();

    expect(session('flash.type'))->toBe('error')
        ->and(session('flash.message'))->toContain('SDAO member')
        ->and(missingApproverSnapshot())->toBe($before);
});

test('the mobile api answers an approval into an empty step with a 422 that names the role', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $chair = User::where('email', 'chair-cs@nu-lipa.edu.ph')->firstOrFail();

    $document = draftDocument(FormType::ActivityProposal, ProposalVariant::RegularOnCalendar, $org);
    $this->engine->submit($document, $this->student);
    $chair->forceFill(['deactivated_at' => now()])->save();
    $before = missingApproverSnapshot();

    $this->withHeaders(['Authorization' => 'Bearer '.$adviser->createToken('Phone')->plainTextToken])
        ->postJson('/api/documents/'.ProposalReference::format($document).'/approve', [])
        ->assertStatus(422)
        ->assertJsonPath('errors.approve.0', fn ($message) => str_contains($message, 'program chair'));

    expect(missingApproverSnapshot())->toBe($before);
});

function missingApproverApprovedActivity(Organization $org): CalendarActivity
{
    $doc = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Approved Calendar',
        'status' => DocumentStatus::Approved,
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);
    $calendar = ActivityCalendar::create([
        'document_id' => $doc->id,
        'academic_year' => AcademicYear::current(),
        'term' => 'first_term',
    ]);

    return CalendarActivity::create([
        'activity_calendar_id' => $calendar->id,
        'name' => 'Missing Approver Event',
        'venue' => 'Main Hall',
        'activity_date' => '2026-11-15',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ]);
}

/** A fully approved proposal, ready for a report to be filed against it. */
function missingApproverApprovedProposal($test): ActivityProposal
{
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $activity = missingApproverApprovedActivity($org);

    $draft = app(StartProposalDraft::class)->execute(
        actor: $test->student,
        organization: $org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );
    $document = app(SubmitActivityProposal::class)->execute(
        actor: $test->student,
        document: $draft,
        objectives: "Overall Goal\n\nSpecific Objectives",
    )['document'];

    advanceTo($test->engine, $test->resolver, $document->refresh(), 99);

    return $document->refresh()->activityProposal()->firstOrFail();
}

/** A prior approved registration, so the org may renew (3rd term). */
function missingApproverPriorRegistration(Organization $org, User $actor): void
{
    $document = Document::create([
        'form_type' => FormType::OrganizationRegistration,
        'variant' => null,
        'title' => "Organization Registration — {$org->name}",
        'status' => DocumentStatus::Draft,
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => $actor->id,
    ]);
    OrganizationRegistrationDetail::create([
        'document_id' => $document->id,
        'organization_type' => OrganizationType::CoCurricular->value,
        'purpose_of_organization' => 'Original description.',
        'contact_person' => 'Original Person',
        'contact_no' => '09171111111',
        'email_address' => 'original@example.test',
        'date_organized' => '2020-06-01',
        'adviser_id' => null,
    ]);

    $engine = app(ApprovalEngine::class);
    $engine->submit($document, $actor);
    $document->refresh();
    $engine->approve($document, User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail());
    $document->refresh();
    $engine->approve($document, User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail());

    CurrentPeriod::set(new AcademicPeriod(CurrentPeriod::get()->academicYear, Term::ThirdTerm));
}
