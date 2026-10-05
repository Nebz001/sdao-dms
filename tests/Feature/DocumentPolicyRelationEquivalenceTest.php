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
use Illuminate\Support\Facades\Gate;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * DocumentPolicy::review()/isChainApprover()/hasActedOn() now read from an
 * already-loaded workflowTemplate.steps/transitions/stepApprovals relation
 * when one is present (the mobile queue's bulk eager load), instead of
 * always issuing their own query. This is a pure performance change — it
 * must never change WHO the policy lets in.
 *
 * For every ability, every role, and every document state below, this
 * compares a freshly-fetched (nothing eager-loaded) Document against the
 * same row re-fetched WITH every relation the fast path can use, and
 * asserts identical Gate results. Both paths are exercised throughout: a
 * Draft/Rejected snapshot never has workflowTemplate.steps loaded-and-used
 * this way (short-circuits earlier), while InReview/Returned/Approved
 * snapshots exercise the loaded branch in stepAtPosition(),
 * reachedPositionFor() and stepsUpToPosition() directly.
 */
beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);

    $this->startDraft = app(StartProposalDraft::class);
    $this->submitProposal = app(SubmitActivityProposal::class);
    $this->engine = app(ApprovalEngine::class);

    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();

    $this->users = [
        'student' => User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail(),
        'secretary' => User::where('email', 'student-delta@students.nu-lipa.edu.ph')->firstOrFail(),
        'adviser' => User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail(),
        'chair' => User::where('email', 'chair-cs@nu-lipa.edu.ph')->firstOrFail(),
        'dean' => User::where('email', 'dean-ccit@nu-lipa.edu.ph')->firstOrFail(),
        'sdaoA' => User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail(),
        'sdaoB' => User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail(),
        'asstDirector' => User::where('email', 'asst-director@nu-lipa.edu.ph')->firstOrFail(),
        'academicDirector' => User::where('email', 'academic-director@nu-lipa.edu.ph')->firstOrFail(),
        'executiveDirector' => User::where('email', 'executive-director@nu-lipa.edu.ph')->firstOrFail(),
    ];

    $this->abilities = ['view', 'reviewView', 'review', 'isChainApprover', 'viewArchive'];
});

function equivApprovedActivity(Organization $org): CalendarActivity
{
    $doc = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Equiv Approved Calendar',
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
        'name' => 'Equivalence Test Event',
        'venue' => 'Equivalence Hall',
        'activity_date' => '2026-10-20',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ]);
}

function equivSubmittedProposal($test): Document
{
    $activity = equivApprovedActivity($test->org);

    $draft = $test->startDraft->execute(
        actor: $test->users['student'],
        organization: $test->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    return $test->submitProposal->execute(
        actor: $test->users['student'],
        document: $draft,
        objectives: "Overall Goal\n\nSpecific Objectives",
    )['document'];
}

/**
 * Compares every ability, for every seeded user, between a freshly-fetched
 * Document (no relations loaded — the current query path) and the same
 * row re-fetched with every relation the fast path can use (the loaded
 * path). Fails with a precise message identifying exactly which
 * ability/user/state disagreed.
 */
function assertPolicyEquivalentForAllUsers($test, int $documentId, string $label): void
{
    $unloaded = Document::find($documentId);

    $loaded = Document::find($documentId)->load([
        'organization',
        'workflowTemplate.steps',
        'transitions',
        'stepApprovals',
    ]);

    foreach ($test->users as $userLabel => $user) {
        foreach ($test->abilities as $ability) {
            $unloadedResult = Gate::forUser($user)->allows($ability, $unloaded);
            $loadedResult = Gate::forUser($user)->allows($ability, $loaded);

            expect($loadedResult)->toBe(
                $unloadedResult,
                "[{$label}] ability '{$ability}' for '{$userLabel}' differs: unloaded=".
                    ($unloadedResult ? 'true' : 'false').', loaded='.
                    ($loadedResult ? 'true' : 'false'),
            );
        }
    }
}

test('policy results are identical loaded vs unloaded across every state of a Regular 7-step proposal', function () {
    $doc = equivSubmittedProposal($this);

    // Draft-adjacent: workflow_template_id/current_step_position both set
    // now (submit already ran), so this is really "InReview at step 1" —
    // captured explicitly below as step 1 of the loop.
    $stepUsers = [
        1 => 'adviser',
        2 => 'chair',
        3 => 'dean',
        4 => 'sdaoA', // sdaoA approves first — quorum not yet met, stays at 4
        5 => 'asstDirector', // sdaoB's approval (still position 4) advances to 5
        6 => 'academicDirector',
        7 => 'executiveDirector',
    ];

    foreach ($stepUsers as $position => $_) {
        assertPolicyEquivalentForAllUsers($this, $doc->id, "InReview @ position {$position}");

        if ($position === 4) {
            // SDAO quorum of 2: first approval is partial (stays at 4).
            $this->engine->approve($doc, $this->users['sdaoA']);
            $doc->refresh();

            assertPolicyEquivalentForAllUsers($this, $doc->id, 'InReview @ position 4 (first of two SDAO approvals)');

            $this->engine->approve($doc, $this->users['sdaoB']);
        } else {
            $this->engine->approve($doc, $this->users[$stepUsers[$position]]);
        }

        $doc->refresh();
    }

    // Fully approved now (no current step).
    expect($doc->status)->toBe(DocumentStatus::Approved);
    assertPolicyEquivalentForAllUsers($this, $doc->id, 'Approved (terminal)');
});

test('policy results are identical loaded vs unloaded for a Returned document', function () {
    $doc = equivSubmittedProposal($this);

    $this->engine->approve($doc, $this->users['adviser']);
    $doc->refresh();

    $this->engine->returnForRevision(
        $doc,
        $this->users['chair'],
        'Please fix the budget section.',
        ['budget'],
    );
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Returned);
    assertPolicyEquivalentForAllUsers($this, $doc->id, 'Returned @ position 2');
});

test('policy results are identical loaded vs unloaded for a Rejected document', function () {
    $doc = equivSubmittedProposal($this);

    $this->engine->reject($doc, $this->users['adviser'], 'Not aligned with academic objectives.');
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Rejected);
    assertPolicyEquivalentForAllUsers($this, $doc->id, 'Rejected (terminal)');
});

test('policy results are identical loaded vs unloaded for a Draft document', function () {
    $activity = equivApprovedActivity($this->org);

    $draft = $this->startDraft->execute(
        actor: $this->users['student'],
        organization: $this->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    expect($draft->status)->toBe(DocumentStatus::Draft);
    assertPolicyEquivalentForAllUsers($this, $draft->id, 'Draft');
});
