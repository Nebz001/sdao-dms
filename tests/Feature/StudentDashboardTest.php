<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Enums\Term;
use App\Enums\TransitionAction;
use App\Models\ActivityCalendar;
use App\Models\ActivityProposal;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\User;
use App\Organizations\StudentDashboardData;
use App\Support\AcademicPeriod;
use App\Support\AcademicYear;
use App\Support\CurrentPeriod;
use Database\Seeders\WorkflowTemplateSeeder;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * Covers App\Organizations\StudentDashboardData directly (constructing it
 * against a real OrganizationMembership/AcademicPeriod), the same unit-level
 * style ApproverDashboardTest already uses for its approver-side sibling —
 * exercising the read model's own rules rather than only the controller's
 * HTTP wiring (DashboardTest covers that boundary).
 */
beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);

    // A fixed, known period — deterministic regardless of the real wall
    // clock, and NOT 3rd term by default so renewal-season assertions below
    // are explicit about which term they need.
    CurrentPeriod::set(new AcademicPeriod('2026-2027', Term::FirstTerm));

    $this->computingSociety = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->studentBeta = User::where('email', 'student-beta@students.nu-lipa.edu.ph')->firstOrFail();
    $this->membershipAlpha = $this->studentAlpha->organizationMemberships()->active()->firstOrFail();
    $this->membershipBeta = $this->studentBeta->organizationMemberships()->active()->firstOrFail();
});

/**
 * Drives a fresh on-calendar proposal for the given org through its full
 * regular-school chain to Approved — same approve list
 * AfterActivityReportPickerTest's own helper uses, duplicated (not shared)
 * since Pest loads every test file's top-level functions into one global
 * namespace and a shared name would collide.
 */
function driveStudentDashboardProposalToApproved(Organization $org, User $actor, string $activityName, string $activityDate): ActivityProposal
{
    $engine = app(ApprovalEngine::class);

    $calendarDoc = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Approved Calendar',
        'status' => DocumentStatus::Approved,
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);
    $cal = ActivityCalendar::create([
        'document_id' => $calendarDoc->id,
        'academic_year' => AcademicYear::current(),
        'term' => 'first_term',
    ]);
    $activity = CalendarActivity::create([
        'activity_calendar_id' => $cal->id,
        'name' => $activityName,
        'venue' => 'Main Hall '.$activityName,
        'activity_date' => $activityDate,
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);

    $draft = app(StartProposalDraft::class)->execute(
        actor: $actor,
        organization: $org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );
    ['document' => $proposalDoc] = app(SubmitActivityProposal::class)->execute(
        actor: $actor,
        document: $draft,
        objectives: "Overall Goal\n\nSpecific Objectives",
    );

    $adviserEmail = $org->name === 'IT Guild' ? 'adviser-two@nu-lipa.edu.ph' : 'adviser-one@nu-lipa.edu.ph';
    $chairEmail = $org->name === 'IT Guild' ? 'chair-it@nu-lipa.edu.ph' : 'chair-cs@nu-lipa.edu.ph';

    foreach ([
        $adviserEmail,
        $chairEmail,
        'dean-ccit@nu-lipa.edu.ph',
        'sdao-a@nu-lipa.edu.ph',
        'sdao-b@nu-lipa.edu.ph',
        'asst-director@nu-lipa.edu.ph',
        'academic-director@nu-lipa.edu.ph',
        'executive-director@nu-lipa.edu.ph',
    ] as $email) {
        $engine->approve($proposalDoc, User::where('email', $email)->firstOrFail());
        $proposalDoc->refresh();
    }

    return $proposalDoc->activityProposal()->firstOrFail();
}

test('meta reflects the organization name and the current admin-controlled period', function () {
    $dashboard = StudentDashboardData::for($this->membershipAlpha, CurrentPeriod::get());

    expect($dashboard->meta())->toMatchArray([
        'organizationName' => 'Computing Society',
        'academicYear' => '2026-2027',
        'termLabel' => Term::FirstTerm->label(),
    ]);
});

test('needsAction lists a returned document with its comment and returner, in a separate group from activity proposal drafts', function () {
    $adviserOne = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    // An untouched draft — step 1 only, never submitted to step 2.
    $draft = app(StartProposalDraft::class)->execute(
        actor: $this->studentAlpha,
        organization: $this->computingSociety,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => calendarActivityFor($this->computingSociety)->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    // A second, separate proposal — submitted to step 2, then returned.
    $submittedDraft = app(StartProposalDraft::class)->execute(
        actor: $this->studentAlpha,
        organization: $this->computingSociety,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => calendarActivityFor($this->computingSociety)->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );
    ['document' => $proposalDoc] = app(SubmitActivityProposal::class)->execute(
        actor: $this->studentAlpha,
        document: $submittedDraft,
        objectives: "Overall Goal\n\nSpecific Objectives",
    );

    app(ApprovalEngine::class)->returnForRevision(
        $proposalDoc,
        $adviserOne,
        comment: 'Please add more detail to the budget.',
        flaggedSections: ['budget'],
    );
    $proposalDoc->refresh();

    $dashboard = StudentDashboardData::for($this->membershipAlpha, CurrentPeriod::get());
    $result = $dashboard->needsAction();

    // draft (the standalone, never-submitted one) + the returned proposal.
    expect($result['total'])->toBe(2);

    $returnedItem = collect($result['items'])->firstWhere('kind', 'returned');
    $draftItem = collect($result['items'])->firstWhere('kind', 'draft');

    expect($returnedItem)
        ->not->toBeNull()
        ->and($returnedItem['comment'])->toBe('Please add more detail to the budget.')
        ->and($returnedItem['returnedByName'])->toBe($adviserOne->name)
        ->and($returnedItem['flaggedCount'])->toBe(1)
        ->and($returnedItem['actionLabel'])->toBe('Fix and resubmit');

    expect($draftItem)
        ->not->toBeNull()
        ->and($draftItem['id'])->toBe('doc-'.$draft->id)
        ->and($draftItem['actionLabel'])->toBe('Continue draft');
});

test('tracker shows an in-review document\'s steps, including the SDAO 2-of-2 requirement once one of the two members has approved', function () {
    $proposal = driveProposalPartwayToSdao($this->computingSociety, $this->studentAlpha);
    $proposalDoc = $proposal->document;

    // One of the two required SDAO approvals lands — the step is still
    // "current" (quorum not yet met), with approvalsSoFar reflecting it.
    app(ApprovalEngine::class)->approve($proposalDoc, User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail());
    $proposalDoc->refresh();

    $dashboard = StudentDashboardData::for($this->membershipAlpha, CurrentPeriod::get());
    $result = $dashboard->tracker();

    expect($result['total'])->toBe(1);
    $row = $result['items'][0];
    expect($row['status'])->toBe('in_review')
        ->and($row['waitingSince'])->not->toBeNull();

    $sdaoStep = collect($row['steps'])->firstWhere('label', 'SDAO Member');
    expect($sdaoStep)
        ->not->toBeNull()
        ->and($sdaoStep['state'])->toBe('current')
        ->and($sdaoStep['requiredApprovals'])->toBe(2)
        ->and($sdaoStep['approvalsSoFar'])->toBe(1);
});

test('kpis tally submitted, approved, and needs-revision counts within the current academic year only', function () {
    [$start] = CurrentPeriod::get()->academicYearRange();

    $doc = Document::create([
        'form_type' => FormType::OrganizationRenewal,
        'variant' => null,
        'title' => 'In-Year Submission',
        'status' => DocumentStatus::InReview,
        'current_step_position' => 1,
        'organization_id' => $this->computingSociety->id,
        'workflow_template_id' => null,
        'submitted_by' => $this->studentAlpha->id,
    ]);
    DocumentTransition::create([
        'document_id' => $doc->id,
        'actor_id' => $this->studentAlpha->id,
        'action' => TransitionAction::Submitted,
        'from_status' => DocumentStatus::Draft,
        'to_status' => DocumentStatus::InReview,
        'step_position' => 1,
        'created_at' => $start->copy()->addDay(),
    ]);

    // A submission from a PRIOR academic year — must not count.
    $priorDoc = Document::create([
        'form_type' => FormType::OrganizationRenewal,
        'variant' => null,
        'title' => 'Prior Year Submission',
        'status' => DocumentStatus::Approved,
        'current_step_position' => null,
        'organization_id' => $this->computingSociety->id,
        'workflow_template_id' => null,
        'submitted_by' => $this->studentAlpha->id,
    ]);
    DocumentTransition::create([
        'document_id' => $priorDoc->id,
        'actor_id' => $this->studentAlpha->id,
        'action' => TransitionAction::Submitted,
        'from_status' => DocumentStatus::Draft,
        'to_status' => DocumentStatus::InReview,
        'step_position' => 1,
        'created_at' => $start->copy()->subYear(),
    ]);

    $dashboard = StudentDashboardData::for($this->membershipAlpha, CurrentPeriod::get());
    $kpis = $dashboard->kpis();

    expect($kpis['totalSubmitted']['count'])->toBe(1)
        ->and($kpis['inReview']['count'])->toBe(1)
        ->and($kpis['needsRevision']['count'])->toBe(0);
});

test('checklist marks renewal not applicable outside 3rd term, and activity calendar as still needed', function () {
    $dashboard = StudentDashboardData::for($this->membershipAlpha, CurrentPeriod::get());
    $items = collect($dashboard->requirements()['items'])->keyBy('key');

    expect($items['coverage']['tier'])->toBe('required')
        ->and($items['renewal']['tier'])->toBe('conditional')
        ->and($items['renewal']['state'])->toBe('not_applicable')
        ->and($items['activity_calendar']['tier'])->toBe('required')
        ->and($items['activity_calendar']['state'])->toBe('action_needed')
        ->and($items['reports']['state'])->toBe('not_applicable');
});

test('quickSubmit always disables registration and always enables activity proposal, applying real eligibility elsewhere', function () {
    $dashboard = StudentDashboardData::for($this->membershipAlpha, CurrentPeriod::get());
    $tiles = collect($dashboard->quickSubmit())->keyBy('formType');

    expect($tiles['organization_registration']['enabled'])->toBeFalse()
        ->and($tiles['organization_registration']['reason'])->toBe('Already registered')
        ->and($tiles['organization_renewal']['enabled'])->toBeFalse()
        ->and($tiles['organization_renewal']['reason'])->toBe('No approved registration to renew')
        ->and($tiles['activity_proposal']['enabled'])->toBeTrue()
        ->and($tiles['activity_proposal']['reason'])->toBeNull()
        ->and($tiles['after_activity_report']['enabled'])->toBeFalse();
});

test('upcoming activities lists only an approved activity within 30 days, and a past approved activity with no report appears as a report due', function () {
    $today = now();

    driveStudentDashboardProposalToApproved(
        $this->computingSociety,
        $this->studentAlpha,
        'Soon Activity',
        $today->copy()->addDays(10)->toDateString(),
    );
    driveStudentDashboardProposalToApproved(
        $this->computingSociety,
        $this->studentAlpha,
        'Far Future Activity',
        $today->copy()->addDays(60)->toDateString(),
    );
    driveStudentDashboardProposalToApproved(
        $this->computingSociety,
        $this->studentAlpha,
        'Past Activity',
        $today->copy()->subDays(5)->toDateString(),
    );

    $dashboard = StudentDashboardData::for($this->membershipAlpha, CurrentPeriod::get());
    $result = $dashboard->upcomingActivities();

    $upcomingTitles = collect($result['upcoming'])->pluck('title');
    expect($upcomingTitles)->toContain('Soon Activity')
        ->and($upcomingTitles)->not->toContain('Far Future Activity')
        ->and($upcomingTitles)->not->toContain('Past Activity');

    $reportsDueTitles = collect($result['reportsDue'])->pluck('title');
    expect($reportsDueTitles)->toContain('Past Activity')
        ->and($reportsDueTitles)->not->toContain('Soon Activity');
});

test('submissionsOverTime tallies a Submitted transition into its correct monthly bucket', function () {
    $submittedAt = now()->subMonths(2)->startOfMonth()->addDays(3);

    $doc = Document::create([
        'form_type' => FormType::OrganizationRenewal,
        'variant' => null,
        'title' => 'Bucketed Submission',
        'status' => DocumentStatus::InReview,
        'current_step_position' => 1,
        'organization_id' => $this->computingSociety->id,
        'workflow_template_id' => null,
        'submitted_by' => $this->studentAlpha->id,
    ]);
    DocumentTransition::create([
        'document_id' => $doc->id,
        'actor_id' => $this->studentAlpha->id,
        'action' => TransitionAction::Submitted,
        'from_status' => DocumentStatus::Draft,
        'to_status' => DocumentStatus::InReview,
        'step_position' => 1,
        'created_at' => $submittedAt,
    ]);

    $dashboard = StudentDashboardData::for($this->membershipAlpha, CurrentPeriod::get());
    $months = collect($dashboard->submissionsOverTime());

    $bucket = $months->firstWhere('monthStart', $submittedAt->copy()->startOfMonth()->toDateString());

    expect($bucket)->not->toBeNull()
        ->and($bucket['count'])->toBe(1)
        ->and($months->sum('count'))->toBe(1);
});

test('isolation: a document belonging to IT Guild never surfaces on Computing Society\'s dashboard', function () {
    Document::create([
        'form_type' => FormType::OrganizationRenewal,
        'variant' => null,
        'title' => 'IT Guild Returned Renewal',
        'status' => DocumentStatus::Returned,
        'current_step_position' => 1,
        'organization_id' => $this->itGuild->id,
        'workflow_template_id' => null,
        'submitted_by' => $this->studentBeta->id,
    ]);

    $csDashboard = StudentDashboardData::for($this->membershipAlpha, CurrentPeriod::get());
    $itDashboard = StudentDashboardData::for($this->membershipBeta, CurrentPeriod::get());

    expect($csDashboard->needsAction()['total'])->toBe(0)
        ->and($csDashboard->kpis()['inReview']['count'])->toBe(0)
        ->and($itDashboard->needsAction()['total'])->toBe(1);
});

/**
 * A CalendarActivity belonging to an approved Activity Calendar for $org —
 * the on-calendar mode's data dependency for StartProposalDraft.
 */
function calendarActivityFor(Organization $org): CalendarActivity
{
    $calendarDoc = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Approved Calendar',
        'status' => DocumentStatus::Approved,
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);
    $cal = ActivityCalendar::create([
        'document_id' => $calendarDoc->id,
        'academic_year' => AcademicYear::current(),
        'term' => 'first_term',
    ]);

    return CalendarActivity::create([
        'activity_calendar_id' => $cal->id,
        'name' => 'Needs Action Test Activity',
        'venue' => 'Main Hall',
        'activity_date' => now()->addDays(15)->toDateString(),
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);
}

/**
 * Drives a fresh on-calendar proposal for $org through adviser -> program
 * chair -> dean, leaving it InReview at the SDAO step (2 required approvers,
 * none yet) — the fixture tracker()'s "N of M" test needs.
 */
function driveProposalPartwayToSdao(Organization $org, User $actor): ActivityProposal
{
    $engine = app(ApprovalEngine::class);
    $activity = calendarActivityFor($org);

    $draft = app(StartProposalDraft::class)->execute(
        actor: $actor,
        organization: $org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );
    ['document' => $proposalDoc] = app(SubmitActivityProposal::class)->execute(
        actor: $actor,
        document: $draft,
        objectives: "Overall Goal\n\nSpecific Objectives",
    );

    $adviserEmail = $org->name === 'IT Guild' ? 'adviser-two@nu-lipa.edu.ph' : 'adviser-one@nu-lipa.edu.ph';
    $chairEmail = $org->name === 'IT Guild' ? 'chair-it@nu-lipa.edu.ph' : 'chair-cs@nu-lipa.edu.ph';

    foreach ([$adviserEmail, $chairEmail, 'dean-ccit@nu-lipa.edu.ph'] as $email) {
        $engine->approve($proposalDoc, User::where('email', $email)->firstOrFail());
        $proposalDoc->refresh();
    }

    return $proposalDoc->activityProposal()->firstOrFail();
}
