<?php

use App\ActivityProposals\ProposalReference;
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
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);

    $this->startDraft = app(StartProposalDraft::class);
    $this->submitProposal = app(SubmitActivityProposal::class);
    $this->engine = app(ApprovalEngine::class);
});

function showTestApprovedActivity(Organization $org, string $name = 'Show Test Event', string $start = '09:00', string $end = '11:00'): CalendarActivity
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
        'venue' => 'Show Test Hall',
        'activity_date' => '2026-11-15',
        'start_time' => $start,
        'end_time' => $end,
    ]);
}

function showTestSubmitProposal($test, Organization $org, User $student, string $title = 'Show Test Event'): Document
{
    $activity = showTestApprovedActivity($org, $title);

    $draft = $test->startDraft->execute(
        actor: $student,
        organization: $org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    return $test->submitProposal->execute(
        actor: $student,
        document: $draft,
        objectives: "Overall Goal\n\nSpecific Objectives",
    )['document'];
}

function showTestToken(User $user): string
{
    return $user->createToken('Phone')->plainTextToken;
}

test('the show response has the full expected shape at step 1', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $doc = showTestSubmitProposal($this, $org, $student);

    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $reference = ProposalReference::format($doc);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.showTestToken($adviser)])
        ->getJson("/api/documents/{$reference}");

    $response->assertOk();
    $response->assertJson([
        'id' => $reference,
        'type' => 'activity_proposal',
        'status' => 'in_review',
        'current_stage' => 'adviser_review',
        'current_stage_label' => 'Adviser Review',
        'current_step' => 1,
        'total_steps' => 7,
        'organization_name' => 'Computing Society',
    ]);

    expect($response->json('id'))->toMatch('/^PROP-\d{4}-\d{3,}$/');
    expect($response->json('submitted_at'))->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/');
    expect($response->json('updated_at'))->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/');
    expect($response->json('activity_proposal.starts_at'))->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/');
    expect($response->json('activity_proposal.partner_organizations'))->toBeArray();
    expect($response->json('activity_proposal.partner_organization_details'))->toBeArray();
    expect($response->json('activity_proposal.revision_sections'))->toHaveCount(12);
});

test('an SHS proposal has 6 total steps and skips straight to principal after the adviser', function () {
    $org = Organization::where('name', 'SHS Student Council')->firstOrFail();
    $student = User::where('email', 'student-gamma@students.nu-lipa.edu.ph')->firstOrFail();
    $doc = showTestSubmitProposal($this, $org, $student, 'SHS Show Test');

    $adviserShs = User::where('email', 'adviser-shs@nu-lipa.edu.ph')->firstOrFail();
    $this->engine->approve($doc, $adviserShs);
    $doc->refresh();

    $principal = User::where('email', 'principal-shs@nu-lipa.edu.ph')->firstOrFail();
    $reference = ProposalReference::format($doc);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.showTestToken($principal)])
        ->getJson("/api/documents/{$reference}");

    $response->assertOk();
    $response->assertJson([
        'current_stage' => 'principal_review',
        'current_step' => 2,
        'total_steps' => 6,
    ]);
});

test('an Extra-Curricular proposal has 5 total steps and skips chair and dean entirely', function () {
    $org = Organization::where('name', 'University Chess Club')->firstOrFail();
    $student = User::where('email', 'student-epsilon@students.nu-lipa.edu.ph')->firstOrFail();
    $doc = showTestSubmitProposal($this, $org, $student, 'Chess Club Show Test');

    $adviserExtra = User::where('email', 'adviser-extracurricular@nu-lipa.edu.ph')->firstOrFail();
    $this->engine->approve($doc, $adviserExtra);
    $doc->refresh();

    $sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $reference = ProposalReference::format($doc);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.showTestToken($sdaoA)])
        ->getJson("/api/documents/{$reference}");

    $response->assertOk();
    $response->assertJson([
        'current_stage' => 'sdao_review',
        'current_step' => 2,
        'total_steps' => 5,
    ]);
});

test('a partial SDAO approval keeps the SDAO stage and reports 1 of 2 approved', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $doc = showTestSubmitProposal($this, $org, $student);

    foreach (['adviser-one@nu-lipa.edu.ph', 'chair-cs@nu-lipa.edu.ph', 'dean-ccit@nu-lipa.edu.ph'] as $email) {
        $this->engine->approve($doc, User::where('email', $email)->firstOrFail());
        $doc->refresh();
    }

    $sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->engine->approve($doc, $sdaoA);
    $doc->refresh();

    expect($doc->current_step_position)->toBe(4);

    $reference = ProposalReference::format($doc);
    $response = $this->withHeaders(['Authorization' => 'Bearer '.showTestToken($sdaoA)])
        ->getJson("/api/documents/{$reference}");

    $response->assertOk();
    $response->assertJson([
        'current_stage' => 'sdao_review',
        'activity_proposal' => [
            'approval' => [
                'stage_label' => 'SDAO Member Approval',
                'approved_count' => 1,
                'total_count' => 2,
            ],
        ],
    ]);
});

test('starts_at/ends_at are identical whether the DB stored H:i:s or H:i', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    $docWithSeconds = showTestSubmitProposal($this, $org, $student, 'Seconds Format');
    DB::table('calendar_activities')
        ->where('id', $docWithSeconds->activityProposal->calendar_activity_id)
        ->update(['start_time' => '09:00:00', 'end_time' => '11:00:00']);

    $docWithoutSeconds = showTestSubmitProposal($this, $org, $student, 'No Seconds Format');
    DB::table('calendar_activities')
        ->where('id', $docWithoutSeconds->activityProposal->calendar_activity_id)
        ->update(['start_time' => '09:00', 'end_time' => '11:00']);

    $refA = ProposalReference::format($docWithSeconds);
    $refB = ProposalReference::format($docWithoutSeconds);

    $responseA = $this->withHeaders(['Authorization' => 'Bearer '.showTestToken($adviser)])->getJson("/api/documents/{$refA}");
    $responseB = $this->withHeaders(['Authorization' => 'Bearer '.showTestToken($adviser)])->getJson("/api/documents/{$refB}");

    expect($responseA->json('activity_proposal.starts_at'))->toBe($responseB->json('activity_proposal.starts_at'));
    expect($responseA->json('activity_proposal.ends_at'))->toBe($responseB->json('activity_proposal.ends_at'));
    // 09:00 Asia/Manila (UTC+8) is 01:00 UTC.
    expect($responseA->json('activity_proposal.starts_at'))->toBe('2026-11-15T01:00:00.000Z');
});

test('404 for an unknown proposal reference', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    $this->withHeaders(['Authorization' => 'Bearer '.showTestToken($adviser)])
        ->getJson('/api/documents/PROP-2026-999999')
        ->assertStatus(404)
        ->assertJson(['message' => 'Activity Proposal not found.']);
});

test('404 for a malformed proposal reference', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    $this->withHeaders(['Authorization' => 'Bearer '.showTestToken($adviser)])
        ->getJson('/api/documents/not-a-real-id')
        ->assertStatus(404);
});

test('404 for the correct id but the wrong year', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $doc = showTestSubmitProposal($this, $org, $student);
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    $wrongYearReference = 'PROP-2099-'.sprintf('%03d', $doc->id);

    $this->withHeaders(['Authorization' => 'Bearer '.showTestToken($adviser)])
        ->getJson("/api/documents/{$wrongYearReference}")
        ->assertStatus(404);
});

test('404 for a document that is not an Activity Proposal, even with a valid-looking reference', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $calendarDoc = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Not a proposal',
        'status' => DocumentStatus::InReview,
        'current_step_position' => 1,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);
    $reference = 'PROP-'.$calendarDoc->created_at->format('Y').'-'.sprintf('%03d', $calendarDoc->id);
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    $this->withHeaders(['Authorization' => 'Bearer '.showTestToken($adviser)])
        ->getJson("/api/documents/{$reference}")
        ->assertStatus(404);
});

test('403 for an approver whose step has not been reached yet', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $doc = showTestSubmitProposal($this, $org, $student); // sits at step 1 (adviser)

    $dean = User::where('email', 'dean-ccit@nu-lipa.edu.ph')->firstOrFail();
    $reference = ProposalReference::format($doc);

    $this->withHeaders(['Authorization' => 'Bearer '.showTestToken($dean)])
        ->getJson("/api/documents/{$reference}")
        ->assertStatus(403);
});

test('history stages are consistent through a full climb to Approved', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $doc = showTestSubmitProposal($this, $org, $student);

    $emails = [
        'adviser-one@nu-lipa.edu.ph',
        'chair-cs@nu-lipa.edu.ph',
        'dean-ccit@nu-lipa.edu.ph',
        'sdao-a@nu-lipa.edu.ph',
        'sdao-b@nu-lipa.edu.ph',
        'asst-director@nu-lipa.edu.ph',
        'academic-director@nu-lipa.edu.ph',
        'executive-director@nu-lipa.edu.ph',
    ];
    foreach ($emails as $email) {
        $this->engine->approve($doc, User::where('email', $email)->firstOrFail());
        $doc->refresh();
    }
    expect($doc->status)->toBe(DocumentStatus::Approved);

    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $reference = ProposalReference::format($doc);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.showTestToken($adviser)])
        ->getJson("/api/documents/{$reference}");

    $response->assertOk();
    $history = $response->json('history');

    $allowedStages = [
        'submitted', 'adviser_review', 'program_chair_review', 'dean_review',
        'sdao_review', 'assistant_director_review', 'academic_director_review',
        'executive_director_review', 'completed', 'rejected',
    ];
    foreach ($history as $row) {
        expect($allowedStages)->toContain($row['stage']);
    }

    $submittedRow = collect($history)->firstWhere('action', 'Submitted');
    expect($submittedRow['stage'])->toBe('submitted');
    expect($submittedRow['actor_role'])->toBe('Organization Officer');

    $firstApprove = collect($history)->firstWhere('action', 'Approved');
    expect($firstApprove['stage'])->toBe('adviser_review');
    expect($firstApprove['actor_role'])->toBe('Adviser');

    $firstForward = collect($history)->firstWhere('action', 'Forwarded');
    expect($firstForward['stage'])->toBe('program_chair_review');
    expect($firstForward['actor_role'])->toBe('Adviser');

    $completedRow = collect($history)->firstWhere('action', 'Completed');
    expect($completedRow['stage'])->toBe('completed');
    expect($completedRow['actor_role'])->toBe('Executive Director');
});
