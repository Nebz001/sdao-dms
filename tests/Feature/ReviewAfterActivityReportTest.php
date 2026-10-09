<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Approval\Exceptions\UnauthorizedApproverException;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Models\ActivityCalendar;
use App\Models\ApprovalNotification;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Reports\SubmitAfterActivityReport;
use App\Support\AcademicYear;
use App\Support\NavCounts;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
    $this->outsider = User::factory()->create();
});

/**
 * Drives a fresh on-calendar proposal for Computing Society through its full
 * 7-step regular chain to Approved, then submits an after-activity report
 * against it, returning the (InReview) report Document.
 */
function submittedReportForComputingSociety(): Document
{
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
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
        'name' => 'Review Test Activity',
        'venue' => 'Main Hall',
        'activity_date' => '2026-10-30',
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);

    $draft = app(StartProposalDraft::class)->execute(
        actor: $student,
        organization: $org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );
    ['document' => $proposalDoc] = app(SubmitActivityProposal::class)->execute(
        actor: $student,
        document: $draft,
        objectives: "Overall Goal\n\nSpecific Objectives",
    );

    foreach ([
        'adviser-one@nu-lipa.edu.ph',
        'chair-cs@nu-lipa.edu.ph',
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

    expect($proposalDoc->status)->toBe(DocumentStatus::Approved);

    $proposal = $proposalDoc->activityProposal()->firstOrFail();

    return app(SubmitAfterActivityReport::class)->execute(
        actor: $student,
        proposal: $proposal,
        summary: 'The activity happened as planned.',
        outcomes: 'Great turnout.',
        participantCount: 100,
        attachmentFiles: reportAttachmentFiles(),
    );
}

/**
 * A report whose adviser has already approved, so it now waits at the SDAO
 * step — the state every SDAO-side test below starts from.
 */
function reportAtSdaoStep(): Document
{
    $doc = submittedReportForComputingSociety();

    app(ApprovalEngine::class)->approve($doc, User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail());

    return $doc->refresh();
}

test('a new report goes to the adviser first, then to SDAO', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    $doc = submittedReportForComputingSociety();

    expect($doc->status)->toBe(DocumentStatus::InReview)
        ->and($doc->current_step_position)->toBe(1)
        ->and($doc->workflowTemplate->steps->pluck('role.value')->all())->toBe(['adviser', 'sdao_member']);

    // SDAO cannot act ahead of the adviser.
    expect(fn () => $this->engine->approve($doc, $this->sdaoA))->toThrow(UnauthorizedApproverException::class);

    $this->engine->approve($doc, $adviser);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::InReview)
        ->and($doc->current_step_position)->toBe(2);

    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::InReview)->and($doc->current_step_position)->toBe(2);

    $this->engine->approve($doc, $this->sdaoB);
    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::Approved)->and($doc->current_step_position)->toBeNull();
});

test('the adviser gets the hand-off, the queue entry and the badge; SDAO gets them only after the adviser approves', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $doc = submittedReportForComputingSociety();

    expect(ApprovalNotification::where('document_id', $doc->id)->where('user_id', $adviser->id)->where('step_position', 1)->exists())->toBeTrue()
        ->and(ApprovalNotification::where('document_id', $doc->id)->where('user_id', $this->sdaoA->id)->exists())->toBeFalse();

    $this->actingAs($adviser)->withoutVite()->get(route('review.reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('queue', 1)->where('queue.0.id', $doc->id));

    expect(app(NavCounts::class)->for($adviser)['review']['reports'])->toBe(1)
        ->and(app(NavCounts::class)->for($this->sdaoA)['review']['reports'])->toBe(0);

    $this->engine->approve($doc, $adviser);

    expect(ApprovalNotification::where('document_id', $doc->id)->where('user_id', $this->sdaoA->id)->where('step_position', 2)->exists())->toBeTrue()
        ->and(app(NavCounts::class)->for($adviser)['review']['reports'])->toBe(0)
        ->and(app(NavCounts::class)->for($this->sdaoA)['review']['reports'])->toBe(1);
});

test('the adviser can approve, return and reject a report over HTTP', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    $approved = submittedReportForComputingSociety();
    $this->actingAs($adviser)->withoutVite()
        ->post(route('review.reports.approve', $approved))
        ->assertRedirect(route('review.reports.show', $approved));
    expect($approved->refresh()->current_step_position)->toBe(2);
    // The adviser keeps read access after the report moves on.
    $this->actingAs($adviser)->withoutVite()->get(route('review.reports.show', $approved))->assertOk();
});

test('the adviser can return a report over HTTP', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $doc = submittedReportForComputingSociety();

    $this->actingAs($adviser)->withoutVite()
        ->post(route('review.reports.return', $doc), ['comment' => 'Add the attendance totals.'])
        ->assertRedirect(route('review.reports.show', $doc));

    expect($doc->refresh()->status)->toBe(DocumentStatus::Returned);
});

test('the adviser can reject a report over HTTP', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $doc = submittedReportForComputingSociety();

    $this->actingAs($adviser)->withoutVite()
        ->post(route('review.reports.reject', $doc), ['comment' => 'Not our activity.'])
        ->assertRedirect(route('review.reports.index'));

    expect($doc->refresh()->status)->toBe(DocumentStatus::Rejected);
});

test('a report the adviser returned comes back to the adviser, and SDAO is not consulted until the adviser approves', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $doc = submittedReportForComputingSociety();

    $this->engine->returnForRevision($doc, $adviser, 'Add participant numbers.');
    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::Returned)->and($doc->current_step_position)->toBe(1);

    $this->engine->resubmit($doc, $this->studentAlpha);
    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::InReview)->and($doc->current_step_position)->toBe(1);
    expect(fn () => $this->engine->approve($doc, $this->sdaoA))->toThrow(UnauthorizedApproverException::class);

    // The resubmission hands the report back to the adviser who returned it.
    expect(ApprovalNotification::where('document_id', $doc->id)->where('user_id', $adviser->id)->count())->toBe(2);
});

test('a report SDAO returned resumes at SDAO and the adviser is not asked again', function () {
    $doc = reportAtSdaoStep();

    $this->engine->returnForRevision($doc, $this->sdaoA, 'Please add participant numbers.');
    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::Returned)->and($doc->current_step_position)->toBe(2);

    $this->engine->resubmit($doc, $this->studentAlpha);
    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::InReview)->and($doc->current_step_position)->toBe(2);
});

test('an organization with no active adviser cannot submit a report, and nothing is left behind', function () {
    $proposal = approvedProposalForComputingSociety($this->org, $this->studentAlpha);

    User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail()->forceFill(['deactivated_at' => now()])->save();

    $before = Document::where('form_type', FormType::AfterActivityReport->value)->count();

    expect(fn () => app(SubmitAfterActivityReport::class)->execute(
        actor: $this->studentAlpha,
        proposal: $proposal,
        summary: 'No adviser to receive this.',
        attachmentFiles: reportAttachmentFiles(),
    ))->toThrow(ValidationException::class, 'no active adviser');

    expect(Document::where('form_type', FormType::AfterActivityReport->value)->count())->toBe($before);
});

test('SDAO: first approve is partial — report stays InReview at the SDAO step', function () {
    $doc = reportAtSdaoStep();

    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::InReview);
    expect($doc->current_step_position)->toBe(2);
});

test('SDAO: second approve completes the report — Approved', function () {
    $doc = reportAtSdaoStep();

    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();
    $this->engine->approve($doc, $this->sdaoB);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Approved);
    expect($doc->current_step_position)->toBeNull();
});

test('SDAO: a split decision (one approves, the other returns) sends the report back and clears the partial', function () {
    $doc = reportAtSdaoStep();

    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();
    $this->engine->returnForRevision($doc, $this->sdaoB, 'Add participant numbers.');
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Returned);
    expect($doc->stepApprovals()->where('step_position', 2)->count())->toBe(0);
});

test('SDAO: reject terminates the report', function () {
    $doc = reportAtSdaoStep();

    $this->engine->reject($doc, $this->sdaoA, 'Not sufficient detail.');
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Rejected);
    expect($doc->current_step_position)->toBeNull();
});

test('non-approver user cannot approve a report', function () {
    $doc = submittedReportForComputingSociety();

    expect(fn () => $this->engine->approve($doc, $this->outsider))
        ->toThrow(UnauthorizedApproverException::class);
});

test('review show endpoint returns the report with its linked activity and history', function () {
    $doc = reportAtSdaoStep();

    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route('review.reports.show', $doc))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('review/reports/show')
            ->has('document')
            ->has('report')
            ->has('report.activity.title')
            ->has('view.history')
        );
});

// ── HTTP: quorum-completing approve must not 403 (regression) ────────────────

test('HTTP: first SDAO approve redirects back to the review show page', function () {
    $doc = reportAtSdaoStep();

    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->post(route('review.reports.approve', $doc))
        ->assertRedirect(route('review.reports.show', $doc));
});

test('HTTP: quorum-completing SDAO approve redirects to the queue, not a 403', function () {
    $doc = reportAtSdaoStep();

    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->post(route('review.reports.approve', $doc));

    $this->actingAs($this->sdaoB)
        ->withoutVite()
        ->post(route('review.reports.approve', $doc))
        ->assertRedirect(route('review.reports.index'));

    // Following the redirect must succeed, not 403 — the actual regression.
    $this->actingAs($this->sdaoB)
        ->withoutVite()
        ->get(route('review.reports.index'))
        ->assertOk();
});
