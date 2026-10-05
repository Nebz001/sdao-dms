<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Support\AcademicYear;
use Database\Seeders\WorkflowTemplateSeeder;
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
    $this->chair = User::where('email', 'chair-cs@nu-lipa.edu.ph')->firstOrFail();
    $this->dean = User::where('email', 'dean-ccit@nu-lipa.edu.ph')->firstOrFail();
});

function queueTestApprovedActivity(Organization $org, string $name = 'Queue Test Event'): CalendarActivity
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
    $cal = ActivityCalendar::create([
        'document_id' => $doc->id,
        'academic_year' => AcademicYear::current(),
        'term' => 'first_term',
    ]);

    return CalendarActivity::create([
        'activity_calendar_id' => $cal->id,
        'name' => $name,
        'venue' => 'Queue Test Hall',
        'activity_date' => '2026-11-15',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ]);
}

function queueTestSubmitProposal($test, string $title = 'Queue Test Event'): Document
{
    $activity = queueTestApprovedActivity($test->org, $title);

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

test('the queue includes a proposal at the viewer\'s own step, marked actionable', function () {
    $doc = queueTestSubmitProposal($this);
    $token = $this->adviser->createToken('Phone')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/documents/queue');

    $response->assertOk();
    expect($response->json())->toHaveCount(1);
    expect($response->json('0.status'))->toBe('in_review');
    expect($response->json('0.current_stage'))->toBe('adviser_review');
    expect($response->json('0.permissions.can_act'))->toBeTrue();
});

test('the queue includes a Returned proposal for the returning approver, but can_act is false', function () {
    $doc = queueTestSubmitProposal($this);
    $this->engine->approve($doc, $this->adviser);
    $doc->refresh();
    $this->engine->returnForRevision($doc, $this->chair, 'Fix the budget.', ['budget']);
    $doc->refresh();

    $token = $this->chair->createToken('Phone')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/documents/queue');

    $response->assertOk();
    expect($response->json())->toHaveCount(1);
    expect($response->json('0.status'))->toBe('revision_requested');
    expect($response->json('0.permissions.can_act'))->toBeFalse();
});

test('the queue includes a proposal the viewer already acted on, further up the chain', function () {
    $doc = queueTestSubmitProposal($this);
    $this->engine->approve($doc, $this->adviser); // adviser acted, now at chair's step
    $doc->refresh();

    $token = $this->adviser->createToken('Phone')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/documents/queue');

    $response->assertOk();
    expect($response->json())->toHaveCount(1);
    expect($response->json('0.permissions.can_act'))->toBeFalse();
    expect($response->json('0.permissions.can_view'))->toBeTrue();
});

test('the queue excludes a proposal that has not yet reached the viewer\'s step', function () {
    $doc = queueTestSubmitProposal($this); // sits at step 1 (adviser)
    $token = $this->dean->createToken('Phone')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/documents/queue');

    $response->assertOk();
    expect($response->json())->toBe([]);
});

test('the queue excludes Draft and terminal (Approved/Rejected) documents', function () {
    queueTestApprovedActivity($this->org, 'Unrelated Draft Activity');
    // A draft proposal (step 1 only, never submitted).
    $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => queueTestApprovedActivity($this->org, 'Draft Source')->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    $approvedDoc = queueTestSubmitProposal($this, 'Will Be Approved');
    foreach ([$this->adviser, $this->chair, $this->dean] as $approver) {
        $this->engine->approve($approvedDoc, $approver);
        $approvedDoc->refresh();
    }
    foreach (['sdao-a@nu-lipa.edu.ph', 'sdao-b@nu-lipa.edu.ph', 'asst-director@nu-lipa.edu.ph', 'academic-director@nu-lipa.edu.ph', 'executive-director@nu-lipa.edu.ph'] as $email) {
        $this->engine->approve($approvedDoc, User::where('email', $email)->firstOrFail());
        $approvedDoc->refresh();
    }
    expect($approvedDoc->status)->toBe(DocumentStatus::Approved);

    $rejectedDoc = queueTestSubmitProposal($this, 'Will Be Rejected');
    $this->engine->reject($rejectedDoc, $this->adviser, 'No.');

    $token = $this->adviser->createToken('Phone')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/documents/queue');

    // None of the draft/approved/rejected documents appear.
    $ids = collect($response->json())->pluck('title');
    expect($ids)->not->toContain('Draft Source');
    expect($ids)->not->toContain('Will Be Approved');
    expect($ids)->not->toContain('Will Be Rejected');
});

test('the queue excludes other document types entirely', function () {
    // An in-review Activity Calendar for the same org/adviser chain.
    Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Some Other Calendar',
        'status' => DocumentStatus::InReview,
        'current_step_position' => 1,
        'organization_id' => $this->org->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);

    $token = $this->adviser->createToken('Phone')->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/documents/queue')
        ->assertOk()
        ->assertExactJson([]);
});

test('actionable items sort before non-actionable, then oldest submitted first', function () {
    $older = queueTestSubmitProposal($this, 'Older Non-Actionable');
    $this->engine->approve($older, $this->adviser); // now at chair's step — adviser can only view
    $older->refresh();

    $newerActionable = queueTestSubmitProposal($this, 'Newer Actionable'); // stays at adviser's step

    $token = $this->adviser->createToken('Phone')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/documents/queue');

    $titles = collect($response->json())->pluck('title')->all();
    expect($titles)->toBe(['Newer Actionable', 'Older Non-Actionable']);
});

test('the queue requires authentication', function () {
    $this->getJson('/api/documents/queue')->assertStatus(401);
});

test('a student cannot access the queue', function () {
    $token = $this->student->createToken('Phone')->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/documents/queue')
        ->assertStatus(403);
});
