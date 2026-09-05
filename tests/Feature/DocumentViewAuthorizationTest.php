<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Calendar\SubmitActivityCalendar;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Enums\ProposalCalendarMode;
use App\Enums\Role;
use App\Enums\Term;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Renewals\SubmitOrganizationRenewal;
use App\Reports\SubmitAfterActivityReport;
use App\Support\AcademicPeriod;
use App\Support\AcademicYear;
use App\Support\CurrentPeriod;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Storage;

/**
 * Covers the IDOR gap found in the full gap audit: student-facing show()
 * methods previously had no authorization at all. DocumentPolicy::view()
 * now allows either (a) an affiliated officer of the document's own
 * organization, or (b) an approver whose current step in this document's
 * chain is active right now — for all five form types.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->computingSociety = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail(); // CS officer
    $this->studentBeta = User::where('email', 'student-beta@students.nu-lipa.edu.ph')->firstOrFail(); // IT Guild officer — different org
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->adviserOne = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->chairCs = User::where('email', 'chair-cs@nu-lipa.edu.ph')->firstOrFail();
    $this->deanCcit = User::where('email', 'dean-ccit@nu-lipa.edu.ph')->firstOrFail();
});

/**
 * Builds and dual-approves a registration directly for an org the actor is
 * ALREADY bound to (not via SubmitOrganizationRegistration, which now
 * requires a not-yet-affiliated founding student — Phase 2 item 5). These
 * tests are about view-authorization, not submission mechanics.
 */
function viewAuthApprovedRegistration(Organization $org, User $actor): void
{
    $engine = app(ApprovalEngine::class);
    $sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();

    $doc = viewAuthRegistrationDocument($org, $actor);
    $engine->submit($doc, $actor);
    $doc->refresh();
    $engine->approve($doc, $sdaoA);
    $doc->refresh();
    $engine->approve($doc, $sdaoB);
}

function viewAuthRegistrationDocument(Organization $org, User $actor): Document
{
    $doc = Document::create([
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
        'document_id' => $doc->id,
        'organization_type' => OrganizationType::CoCurricular->value,
        'purpose_of_organization' => 'Original description.',
        'contact_person' => 'Contact Person',
        'contact_no' => '09170000000',
        'email_address' => 'contact@example.test',
        'date_organized' => '2020-06-01',
        'adviser_id' => null,
    ]);

    return $doc;
}

function viewAuthApprovedCalendarActivity(Organization $org, string $name): CalendarActivity
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
        'name' => $name,
        'venue' => 'Main Hall',
        'activity_date' => '2026-10-30',
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);
}

/**
 * Submits an on-calendar activity proposal for the given org — the same
 * inline sequence the "activity proposal show" test above uses, factored out
 * for the reached-step read-access tests below (deliberately not the
 * `authSubmittedProposal()` helper from ProposalReviewAuthorizationTest.php:
 * that function is only defined when both files happen to be loaded in the
 * same run, so this file stays runnable on its own, e.g. via --filter).
 */
function submitViewAuthProposal(Organization $org, User $actor, string $activityName = 'View Auth Proposal Activity'): Document
{
    $activity = viewAuthApprovedCalendarActivity($org, $activityName);

    $draft = app(StartProposalDraft::class)->execute(
        actor: $actor,
        organization: $org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );

    ['document' => $doc] = app(SubmitActivityProposal::class)->execute(
        actor: $actor,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    return $doc;
}

/**
 * Rebinds a role's seat to a brand-new user — the real-world case the
 * "reached step" read-access section below covers: a role holder who
 * inherited the seat AFTER their predecessor acted, and so has no
 * DocumentTransition/DocumentStepApproval row of their own anywhere on this
 * document. Updates the existing assignment rather than adding a second one,
 * because RoleDirectory resolves org-scoped roles with firstOrFail().
 */
function reassignAdviserSeat(Organization $org): User
{
    $successor = User::factory()->create();

    RoleAssignment::query()
        ->where('role', Role::Adviser)
        ->where('organization_id', $org->id)
        ->firstOrFail()
        ->update(['user_id' => $successor->id]);

    return $successor;
}

test('registration show: org officer can view, different-org officer cannot, current-step SDAO can', function () {
    $doc = viewAuthRegistrationDocument($this->computingSociety, $this->studentAlpha);
    $this->engine->submit($doc, $this->studentAlpha);
    $doc->refresh();

    $this->actingAs($this->studentAlpha)->get(route('registrations.show', $doc))->assertOk();
    $this->actingAs($this->studentBeta)->get(route('registrations.show', $doc))->assertForbidden();
    $this->actingAs($this->sdaoA)->get(route('registrations.show', $doc))->assertOk();
});

test('renewal show: org officer can view, different-org officer cannot, current-step SDAO can', function () {
    viewAuthApprovedRegistration($this->computingSociety, $this->studentAlpha);

    // Renewal is only submittable during 3rd-term renewal season.
    CurrentPeriod::set(new AcademicPeriod(CurrentPeriod::get()->academicYear, Term::ThirdTerm));

    $doc = app(SubmitOrganizationRenewal::class)->execute(
        actor: $this->studentAlpha,
        organization: $this->computingSociety,
        organizationType: OrganizationType::CoCurricular,
        purposeOfOrganization: 'Renewed description.',
        contactPerson: 'Contact Person',
        contactNo: '09170000000',
        emailAddress: 'contact@example.test',
        dateOrganized: '2020-06-01',
        attachmentFiles: renewalAttachmentFiles(),
    );

    $this->actingAs($this->studentAlpha)->get(route('renewals.show', $doc))->assertOk();
    $this->actingAs($this->studentBeta)->get(route('renewals.show', $doc))->assertForbidden();
    $this->actingAs($this->sdaoA)->get(route('renewals.show', $doc))->assertOk();
});

test('activity calendar show: org officer can view, different-org officer cannot, current-step SDAO can', function () {
    $result = app(SubmitActivityCalendar::class)->execute(
        actor: $this->studentAlpha,
        organization: $this->computingSociety,
        activities: [[
            'name' => 'Test Event',
            'venue' => 'Gymnasium',
            'activity_date' => '2026-09-15',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]],
    );
    $doc = $result['document'];

    $this->actingAs($this->studentAlpha)->get(route('activity-calendars.show', $doc))->assertOk();
    $this->actingAs($this->studentBeta)->get(route('activity-calendars.show', $doc))->assertForbidden();
    $this->actingAs($this->sdaoA)->get(route('activity-calendars.show', $doc))->assertOk();
});

test('activity proposal show: org officer and the CURRENT step approver can view; different-org officer and a not-yet-current approver cannot', function () {
    $activity = viewAuthApprovedCalendarActivity($this->computingSociety, 'View Auth Activity');

    $draft = app(StartProposalDraft::class)->execute(
        actor: $this->studentAlpha,
        organization: $this->computingSociety,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );
    ['document' => $doc] = app(SubmitActivityProposal::class)->execute(
        actor: $this->studentAlpha,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    // Regular on-calendar chain: step 1 = Adviser.
    expect($doc->current_step_position)->toBe(1);

    $this->actingAs($this->studentAlpha)->get(route('activity-proposals.show', $doc))->assertOk(); // (a) org officer
    $this->actingAs($this->studentBeta)->get(route('activity-proposals.show', $doc))->assertForbidden(); // different org
    $this->actingAs($this->adviserOne)->get(route('activity-proposals.show', $doc))->assertOk(); // (b) current-step approver
    $this->actingAs($this->chairCs)->get(route('activity-proposals.show', $doc))->assertForbidden(); // approver, but not their turn yet
});

// ── Read access for a REACHED step's approver: DocumentPolicy::isChainApprover() ──
// Any approver resolvable for a step this document's chain has already
// passed through keeps read access for as long as the document remains
// active, all the way through and past a terminal state — not just while
// it's their turn. This is deliberately broader than hasActedOn(): it also
// covers a role holder who inherited the seat AFTER their predecessor acted
// (reassignAdviserSeat() below), so it can never be mistaken for merely
// re-testing hasActedOn() under a different name. The "not their turn yet"
// boundary above is untouched: a future step never matches.

test('an approver who inherited a passed step can view the document while it is still in review', function () {
    $doc = submitViewAuthProposal($this->computingSociety, $this->studentAlpha);
    expect($doc->current_step_position)->toBe(1); // Adviser

    $this->engine->approve($doc, $this->adviserOne);
    $doc->refresh();
    expect($doc->current_step_position)->toBe(2); // chain has moved past the Adviser step

    // The seat changes hands AFTER the predecessor acted: the successor has
    // zero transition/step-approval rows, so hasActedOn() can never cover them.
    $successor = reassignAdviserSeat($this->computingSociety);
    expect($successor->can('review', $doc))->toBeFalse(); // not their turn — act gate unchanged

    $this->actingAs($successor)->get(route('review.activity-proposals.show', $doc))->assertOk();
    $this->actingAs($successor)->get(route('activity-proposals.show', $doc))->assertOk();

    // The predecessor keeps access too — they have real rows (hasActedOn()).
    $this->actingAs($this->adviserOne)->get(route('review.activity-proposals.show', $doc))->assertOk();

    // The step-3 dean has still never been reached (max step_position = 2).
    $this->actingAs($this->deanCcit)->get(route('review.activity-proposals.show', $doc))->assertForbidden();
});

test('an approver who inherited a passed step can still view the document after it is approved', function () {
    $doc = submitViewAuthProposal($this->computingSociety, $this->studentAlpha);

    $this->engine->approve($doc, $this->adviserOne);
    $doc->refresh();
    $successor = reassignAdviserSeat($this->computingSociety);

    foreach ([
        'chair-cs@nu-lipa.edu.ph', 'dean-ccit@nu-lipa.edu.ph',
        'sdao-a@nu-lipa.edu.ph', 'sdao-b@nu-lipa.edu.ph',
        'asst-director@nu-lipa.edu.ph', 'academic-director@nu-lipa.edu.ph',
        'executive-director@nu-lipa.edu.ph',
    ] as $email) {
        $this->engine->approve($doc, User::where('email', $email)->firstOrFail());
        $doc->refresh();
    }

    expect($doc->status)->toBe(DocumentStatus::Approved);
    expect($doc->current_step_position)->toBeNull(); // terminal: only transitions remain

    $this->actingAs($successor)->get(route('review.activity-proposals.show', $doc))->assertOk();
    $this->actingAs($successor)->get(route('activity-proposals.show', $doc))->assertOk();
});

test('an approver who inherited a passed step can still view the document after it is rejected', function () {
    $doc = submitViewAuthProposal($this->computingSociety, $this->studentAlpha);

    $this->engine->approve($doc, $this->adviserOne);
    $doc->refresh();
    $successor = reassignAdviserSeat($this->computingSociety);

    $this->engine->reject($doc, $this->chairCs, 'Not approved.');
    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::Rejected);
    expect($doc->current_step_position)->toBeNull();

    $this->actingAs($successor)->get(route('review.activity-proposals.show', $doc))->assertOk();

    // Rejected at step 2 — step 3's dean was never reached, and rejection
    // must not retroactively hand the rest of the chain read access.
    $this->actingAs($this->deanCcit)->get(route('review.activity-proposals.show', $doc))->assertForbidden();
});

test('a past-step approver can download the attachments of a document they can view', function () {
    // Registration's short chain is a single SDAO step, so a newly
    // provisioned SDAO member is a reached-step approver with no rows of
    // their own — the same shape as the proposal tests above, but exercising
    // AttachmentController::download(), which authorizes via `view` alone
    // (not `reviewView`), so a page a user can open must not 403 on its own
    // files.
    $doc = viewAuthRegistrationDocument($this->computingSociety, $this->studentAlpha);
    $this->engine->submit($doc, $this->studentAlpha);
    $doc->refresh();
    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();
    $this->engine->approve($doc, User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail());
    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::Approved);

    $attachment = DocumentAttachment::factory()->create(['document_id' => $doc->id]);
    Storage::disk('supabase')->put($attachment->path, 'fake file contents');

    $newSdaoMember = User::factory()->create();
    $newSdaoMember->roleAssignments()->create(['role' => Role::SdaoMember]);

    $this->actingAs($newSdaoMember)->get(route('attachments.download', $attachment))->assertOk();
});

test('a pre-migration attachment still on the local disk downloads correctly', function () {
    // Rows written before the Supabase migration recorded disk => 'local' —
    // AttachmentController::download() must keep resolving those by their
    // own `disk` column rather than assuming every row is on the current
    // default (supabase) disk.
    $doc = viewAuthRegistrationDocument($this->computingSociety, $this->studentAlpha);
    $this->engine->submit($doc, $this->studentAlpha);
    $doc->refresh();
    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();
    $this->engine->approve($doc, User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail());
    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::Approved);

    $attachment = DocumentAttachment::factory()->local()->create(['document_id' => $doc->id]);
    Storage::disk('local')->put($attachment->path, 'legacy fake file contents');

    $newSdaoMember = User::factory()->create();
    $newSdaoMember->roleAssignments()->create(['role' => Role::SdaoMember]);

    $this->actingAs($newSdaoMember)->get(route('attachments.download', $attachment))->assertOk();
});

test('an approver from a different organization was never on this chain and still gets a real 403', function () {
    $doc = submitViewAuthProposal($this->computingSociety, $this->studentAlpha);
    $this->engine->approve($doc, $this->adviserOne);
    $doc->refresh();

    // adviser-two is IT Guild's adviser (IdentitySeeder) — the Adviser step of
    // THIS document resolves, org-scoped, to Computing Society's adviser only.
    $adviserTwo = User::where('email', 'adviser-two@nu-lipa.edu.ph')->firstOrFail();
    $chairIt = User::where('email', 'chair-it@nu-lipa.edu.ph')->firstOrFail();

    $this->actingAs($adviserTwo)->get(route('review.activity-proposals.show', $doc))->assertForbidden();
    $this->actingAs($adviserTwo)->get(route('activity-proposals.show', $doc))->assertForbidden();
    $this->actingAs($chairIt)->get(route('review.activity-proposals.show', $doc))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('review.activity-proposals.show', $doc))->assertForbidden();
});

// ── Admin document archive: DocumentPolicy::viewArchive() ──────────────────
// hasActedOn() only incidentally covers SDAO for approved documents (every
// SDAO step requires both members, so both always have an acted-on row by
// the time a document is Approved). That coincidence breaks for a document
// rejected before it ever reached the SDAO step, and for a newly provisioned
// SDAO member with no historical rows at all — these tests construct exactly
// those cases directly (no transitions, no step approvals) and pin that
// reviewView() now covers them via viewArchive(), without loosening the
// in-review "approver, but not their turn yet" boundary above.

test('an SDAO member who never acted on a document can open its review show page once it is approved', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->computingSociety->id,
        'status' => DocumentStatus::Approved,
        'current_step_position' => null,
    ]);

    // Newly provisioned SDAO member: no DocumentTransition or
    // DocumentStepApproval row exists for them on this document at all.
    $newSdaoMember = User::factory()->create();
    $newSdaoMember->roleAssignments()->create(['role' => Role::SdaoMember]);

    $this->actingAs($newSdaoMember)->get(route('review.registrations.show', $doc))->assertOk();
});

test('an SDAO member who never acted on a document can open its review show page once it is rejected', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->computingSociety->id,
        'status' => DocumentStatus::Rejected,
        'current_step_position' => null,
    ]);

    $newSdaoMember = User::factory()->create();
    $newSdaoMember->roleAssignments()->create(['role' => Role::SdaoMember]);

    $this->actingAs($newSdaoMember)->get(route('review.registrations.show', $doc))->assertOk();
});

test('viewArchive does not widen access to an in-review document: an SDAO member who is not the current-step approver still gets 403', function () {
    // A short chain's only step IS the SDAO step, so every SDAO member
    // (however newly provisioned) legitimately resolves as a current-step
    // approver there (StepApproverResolver treats Role::SdaoMember as "all
    // SDAO members," per invariant #3 — both are jointly responsible for
    // that step at any time). To exercise a step genuinely NOT YET reached,
    // use the long proposal chain, which starts at the Adviser step — SDAO
    // is several steps away and has not been consulted yet.
    $activity = viewAuthApprovedCalendarActivity($this->computingSociety, 'ViewArchive Boundary Activity');

    $draft = app(StartProposalDraft::class)->execute(
        actor: $this->studentAlpha,
        organization: $this->computingSociety,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );
    ['document' => $doc] = app(SubmitActivityProposal::class)->execute(
        actor: $this->studentAlpha,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    expect($doc->current_step_position)->toBe(1); // Adviser — SDAO not reached yet.

    $newSdaoMember = User::factory()->create();
    $newSdaoMember->roleAssignments()->create(['role' => Role::SdaoMember]);

    $this->actingAs($newSdaoMember)->get(route('review.activity-proposals.show', $doc))->assertForbidden();
});

test('viewArchive is SDAO-scoped, not "any approver": a dean who never acted still gets 403 on an approved document', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->computingSociety->id,
        'status' => DocumentStatus::Approved,
        'current_step_position' => null,
    ]);

    $this->actingAs($this->chairCs)->get(route('review.registrations.show', $doc))->assertForbidden();
});

test('after-activity report show: org officer can view, different-org officer cannot, current-step SDAO can', function () {
    $activity = viewAuthApprovedCalendarActivity($this->computingSociety, 'Report View Auth Activity');

    $draft = app(StartProposalDraft::class)->execute(
        actor: $this->studentAlpha,
        organization: $this->computingSociety,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );
    ['document' => $proposalDoc] = app(SubmitActivityProposal::class)->execute(
        actor: $this->studentAlpha,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
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
        $this->engine->approve($proposalDoc, User::where('email', $email)->firstOrFail());
        $proposalDoc->refresh();
    }

    $proposal = $proposalDoc->activityProposal()->firstOrFail();

    $reportDoc = app(SubmitAfterActivityReport::class)->execute(
        actor: $this->studentAlpha,
        proposal: $proposal,
        summary: 'The activity happened as planned.',
        attachmentFiles: reportAttachmentFiles(),
    );

    $this->actingAs($this->studentAlpha)->get(route('reports.show', $reportDoc))->assertOk();
    $this->actingAs($this->studentBeta)->get(route('reports.show', $reportDoc))->assertForbidden();
    $this->actingAs($this->sdaoA)->get(route('reports.show', $reportDoc))->assertOk();
});
