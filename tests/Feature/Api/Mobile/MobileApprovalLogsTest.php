<?php

use App\ActivityProposals\ProposalReference;
use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Enums\TransitionAction;
use App\Enums\TransitionSource;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\User;
use App\Support\AcademicYear;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);

    $this->startDraft = app(StartProposalDraft::class);
    $this->submitProposal = app(SubmitActivityProposal::class);
    $this->engine = app(ApprovalEngine::class);

    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
});

afterEach(function () {
    Carbon::setTestNow();
});

function approvalLogsSubmitProposal($test, string $title = 'Mobile Logs Event'): Document
{
    $calendarDocument = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Approved Calendar',
        'status' => DocumentStatus::Approved,
        'current_step_position' => null,
        'organization_id' => $test->org->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);
    $calendar = ActivityCalendar::create([
        'document_id' => $calendarDocument->id,
        'academic_year' => AcademicYear::current(),
        'term' => 'first_term',
    ]);
    $activity = CalendarActivity::create([
        'activity_calendar_id' => $calendar->id,
        'name' => $title,
        'venue' => 'Mobile Logs Hall',
        'activity_date' => '2026-11-15',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ]);

    $draft = $test->startDraft->execute(
        actor: $test->student,
        organization: $test->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    return $test->submitProposal->execute(
        actor: $test->student,
        document: $draft,
        objectives: "Overall Goal\n\nSpecific Objectives",
    )['document'];
}

function approvalLogsHeaders(User $user): array
{
    return ['Authorization' => 'Bearer '.$user->createToken('Phone')->plainTextToken];
}

test('a mobile approval is recorded with server-owned provenance and returned as one normalized log', function () {
    $document = approvalLogsSubmitProposal($this, 'IT Week 2026');
    $reference = ProposalReference::format($document);

    $this->withHeaders(approvalLogsHeaders($this->adviser))
        ->postJson("/api/documents/{$reference}/approve", ['source' => 'web'])
        ->assertOk();

    $decision = DocumentTransition::query()
        ->where('document_id', $document->id)
        ->where('action', TransitionAction::Approved->value)
        ->firstOrFail();

    expect($decision->source)->toBe(TransitionSource::Mobile);
    expect(DocumentTransition::query()
        ->where('document_id', $document->id)
        ->where('action', TransitionAction::Advanced->value)
        ->firstOrFail()
        ->source)->toBe(TransitionSource::Mobile);

    $response = $this->withHeaders(approvalLogsHeaders($this->adviser))
        ->getJson('/api/mobile/approval-logs');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', "log-{$decision->id}")
        ->assertJsonPath('data.0.proposal_reference', $reference)
        ->assertJsonPath('data.0.proposal_title', 'IT Week 2026')
        ->assertJsonPath('data.0.action', 'approved')
        ->assertJsonPath('data.0.approver.id', $this->adviser->id)
        ->assertJsonPath('data.0.approver.name', $this->adviser->name)
        ->assertJsonPath('data.0.approver.role', 'Adviser')
        ->assertJsonPath('data.0.from_status', 'in_review')
        ->assertJsonPath('data.0.to_status', 'in_review')
        ->assertJsonPath('data.0.from_stage', 'adviser_review')
        ->assertJsonPath('data.0.to_stage', 'program_chair_review')
        ->assertJsonPath('data.0.remarks', null);

    expect($response->json('data.0.created_at'))->toMatch('/Z$/');
});

test('web decisions and another organization approver data are not exposed', function () {
    $document = approvalLogsSubmitProposal($this);
    $this->engine->approve($document, $this->adviser);

    $this->withHeaders(approvalLogsHeaders($this->adviser))
        ->getJson('/api/mobile/approval-logs')
        ->assertOk()
        ->assertJsonPath('meta.total', 0)
        ->assertJsonCount(0, 'data');

    $other = approvalLogsSubmitProposal($this, 'Private Mobile Decision');
    $reference = ProposalReference::format($other);
    $this->withHeaders(approvalLogsHeaders($this->adviser))
        ->postJson("/api/documents/{$reference}/approve")
        ->assertOk();

    $unrelatedAdviser = User::where('email', 'adviser-two@nu-lipa.edu.ph')->firstOrFail();
    Auth::forgetGuards();
    $this->withHeaders(approvalLogsHeaders($unrelatedAdviser))
        ->getJson('/api/mobile/approval-logs')
        ->assertOk()
        ->assertJsonPath('meta.total', 0)
        ->assertJsonCount(0, 'data');
});

test('return logs expose remarks and support action search and Manila date filters', function () {
    Carbon::setTestNow('2026-10-13 00:30:00 UTC'); // 8:30 AM in Manila

    $document = approvalLogsSubmitProposal($this, 'Searchable Leadership Summit');
    $reference = ProposalReference::format($document);
    $remarks = 'Please revise the budget breakdown.';

    $this->withHeaders(approvalLogsHeaders($this->adviser))
        ->postJson("/api/documents/{$reference}/request-revision", ['remarks' => $remarks])
        ->assertOk();

    $response = $this->withHeaders(approvalLogsHeaders($this->adviser))
        ->getJson('/api/mobile/approval-logs?action=returned&search=Leadership&date_from=2026-10-13&date_to=2026-10-13');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.action', 'returned')
        ->assertJsonPath('data.0.from_status', 'in_review')
        ->assertJsonPath('data.0.to_status', 'returned')
        ->assertJsonPath('data.0.from_stage', 'adviser_review')
        ->assertJsonPath('data.0.to_stage', 'adviser_review')
        ->assertJsonPath('data.0.remarks', $remarks)
        ->assertJsonPath('data.0.created_at', '2026-10-13T00:30:00.000Z');

    $this->withHeaders(approvalLogsHeaders($this->adviser))
        ->getJson('/api/mobile/approval-logs?action=approved')
        ->assertJsonPath('meta.total', 0);
});

test('a mobile rejection is represented as a terminal rejected decision', function () {
    $document = approvalLogsSubmitProposal($this, 'Rejected Mobile Event');
    $reference = ProposalReference::format($document);

    $this->withHeaders(approvalLogsHeaders($this->adviser))
        ->postJson("/api/documents/{$reference}/reject", ['remarks' => 'Not aligned with the objectives.'])
        ->assertOk();

    $this->withHeaders(approvalLogsHeaders($this->adviser))
        ->getJson('/api/mobile/approval-logs?action=rejected')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.action', 'rejected')
        ->assertJsonPath('data.0.from_status', 'in_review')
        ->assertJsonPath('data.0.to_status', 'rejected')
        ->assertJsonPath('data.0.from_stage', 'adviser_review')
        ->assertJsonPath('data.0.to_stage', 'rejected')
        ->assertJsonPath('data.0.remarks', 'Not aligned with the objectives.');
});

test('a final mobile approval folds the completed transition into one log entry', function () {
    $document = approvalLogsSubmitProposal($this, 'Final Mobile Approval');

    foreach ([
        'adviser-one@nu-lipa.edu.ph',
        'chair-cs@nu-lipa.edu.ph',
        'dean-ccit@nu-lipa.edu.ph',
        'sdao-a@nu-lipa.edu.ph',
        'sdao-b@nu-lipa.edu.ph',
        'asst-director@nu-lipa.edu.ph',
        'academic-director@nu-lipa.edu.ph',
    ] as $email) {
        $this->engine->approve($document, User::where('email', $email)->firstOrFail());
        $document->refresh();
    }

    $executiveDirector = User::where('email', 'executive-director@nu-lipa.edu.ph')->firstOrFail();
    $reference = ProposalReference::format($document);
    $this->withHeaders(approvalLogsHeaders($executiveDirector))
        ->postJson("/api/documents/{$reference}/approve")
        ->assertOk();

    $this->withHeaders(approvalLogsHeaders($executiveDirector))
        ->getJson('/api/mobile/approval-logs')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.action', 'approved')
        ->assertJsonPath('data.0.approver.role', 'Executive Director')
        ->assertJsonPath('data.0.from_status', 'in_review')
        ->assertJsonPath('data.0.to_status', 'approved')
        ->assertJsonPath('data.0.from_stage', 'executive_director_review')
        ->assertJsonPath('data.0.to_stage', 'completed');
});

test('approval logs are protected and validate filters', function () {
    $this->getJson('/api/mobile/approval-logs')->assertUnauthorized();

    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->withHeaders(approvalLogsHeaders($student))
        ->getJson('/api/mobile/approval-logs')
        ->assertForbidden();

    Auth::forgetGuards();
    $this->withHeaders(approvalLogsHeaders($this->adviser))
        ->getJson('/api/mobile/approval-logs?action=deleted&date_from=10-13-2026')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['action', 'date_from']);
});

test('approval logs paginate twenty entries at a time', function () {
    $document = approvalLogsSubmitProposal($this);

    foreach (range(1, 21) as $offset) {
        DocumentTransition::create([
            'document_id' => $document->id,
            'actor_id' => $this->adviser->id,
            'action' => TransitionAction::Approved,
            'source' => TransitionSource::Mobile,
            'from_status' => DocumentStatus::InReview,
            'to_status' => DocumentStatus::InReview,
            'step_position' => 1,
            'comment' => null,
            'created_at' => now()->addSeconds($offset),
        ]);
    }

    $headers = approvalLogsHeaders($this->adviser);
    $this->withHeaders($headers)
        ->getJson('/api/mobile/approval-logs')
        ->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonPath('meta.total', 21);

    $this->withHeaders($headers)
        ->getJson('/api/mobile/approval-logs?page=2')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.current_page', 2);
});
