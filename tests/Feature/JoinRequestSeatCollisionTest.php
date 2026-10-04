<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\JoinRequestStatus;
use App\Enums\OfficerPosition;
use App\Enums\OrganizationType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationJoinRequest;
use App\Models\OrganizationMembership;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use App\Notifications\JoinRequestWithdrawnNotification;
use App\Notifications\OfficerSeatGrantedNotification;
use App\Organizations\Admin\ApproveOfficerChange;
use App\Organizations\ApproveJoinRequest;
use App\Organizations\BindOrganizationOfficer;
use App\Organizations\DeclineJoinRequest;
use App\Organizations\RequestOfficerChange;
use App\Organizations\RequestToJoinOrganization;
use App\Registrations\ApproveOrganizationRegistration;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * A student must never end up with a second seat through a join request that
 * was filed BEFORE they got a seat another way. Production proved the old gap:
 * a student held both President and Secretary of one org. Two defences:
 *   1. gaining a seat (bind / approved change / founding) withdraws their
 *      pending join requests;
 *   2. ApproveJoinRequest itself refuses a student who already holds any seat.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->adviserTwo = User::where('email', 'adviser-two@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
    $this->president = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->secretary = User::where('email', 'student-delta@students.nu-lipa.edu.ph')->firstOrFail();
    // Free Computing Society's Secretary seat so there is a position to ask for.
    $this->secretary->organizationMemberships()->active()->update(['is_active' => false, 'ended_at' => now()]);
    $this->student = User::factory()->create(['account_status' => 'verified']);
});

function fileJoin(User $student, Organization $org): OrganizationJoinRequest
{
    return app(RequestToJoinOrganization::class)->execute($student, $org);
}

// ── The exact production sequence ───────────────────────────────────────────

test('REGRESSION: join request filed, adviser binds the student as President, then approving it as Secretary is refused with a clear message', function () {
    Notification::fake();
    $join = fileJoin($this->student, $this->org);

    app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, $this->student, OfficerPosition::President);

    // The bind closed the pending request instead of leaving it to collide later.
    $join->refresh();
    expect($join->status)->toBe(JoinRequestStatus::Withdrawn);
    expect($join->decided_at)->not->toBeNull();
    expect($join->decided_by)->toBeNull();
    expect($join->decision_comment)->toContain('became an officer of this organization');

    // The adviser, working from a stale page, still tries to approve it.
    $message = null;
    try {
        app(ApproveJoinRequest::class)->execute($this->adviser, $join, OfficerPosition::Secretary);
    } catch (ValidationException $e) {
        $message = $e->errors()['join_request'][0];
    }

    expect($message)->toContain('closed automatically')->toContain($this->student->name)->not->toContain('Someone else just changed this seat');
    expect(OrganizationMembership::where('user_id', $this->student->id)->active()->count())->toBe(1);
    expect(OrganizationMembership::where('user_id', $this->student->id)->active()->value('position'))->toBe(OfficerPosition::President);
});

test('REGRESSION over HTTP: the stale approve shows the clear message, not a collision error or a 500', function () {
    $this->withoutVite();
    $join = fileJoin($this->student, $this->org);
    app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, $this->student, OfficerPosition::President);

    $this->actingAs($this->adviser)
        ->from(route('review.join-requests.index'))
        ->post(route('review.join-requests.approve', $join), ['position' => 'secretary'])
        ->assertRedirect(route('review.join-requests.index'))
        ->assertSessionHasErrors('join_request');

    expect(session('errors')->first('join_request'))->toContain('closed automatically')->not->toContain('Someone else just changed this seat');
});

// ── The second defence: refuse a student who already holds a seat ───────────

test('a still-pending request is refused when the student already holds a seat in THIS org (legacy rows, anything that slips past the withdrawal)', function () {
    $join = fileJoin($this->student, $this->org);
    // Simulate a path that gave them a seat without withdrawing the request.
    OrganizationMembership::create([
        'user_id' => $this->student->id, 'organization_id' => $this->org->id, 'position' => 'president',
        'academic_year' => '2026-2027', 'is_active' => false, 'started_at' => now(),
    ]);
    $this->president->organizationMemberships()->active()->update(['is_active' => false, 'ended_at' => now()]);
    OrganizationMembership::where('user_id', $this->student->id)->update(['is_active' => true]);

    expect($join->fresh()->status)->toBe(JoinRequestStatus::Pending);

    expect(fn () => app(ApproveJoinRequest::class)->execute($this->adviser, $join->fresh(), OfficerPosition::Secretary))
        ->toThrow(ValidationException::class, 'is already President of this organization');
    expect(OrganizationMembership::where('user_id', $this->student->id)->active()->count())->toBe(1);
});

test('a still-pending request is refused when the student holds a seat in a different org', function () {
    $join = fileJoin($this->student, $this->org);
    OrganizationMembership::create([
        'user_id' => $this->student->id, 'organization_id' => $this->itGuild->id, 'position' => 'secretary',
        'academic_year' => '2026-2027', 'is_active' => true, 'started_at' => now(),
    ]);

    expect(fn () => app(ApproveJoinRequest::class)->execute($this->adviser, $join->fresh(), OfficerPosition::Secretary))
        ->toThrow(ValidationException::class, 'already an active officer of a different organization');
});

test('declining a request the system already closed says so', function () {
    $join = fileJoin($this->student, $this->org);
    app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, $this->student, OfficerPosition::President);

    expect(fn () => app(DeclineJoinRequest::class)->execute($this->adviser, $join->fresh()))
        ->toThrow(ValidationException::class, 'closed automatically');
});

// ── Withdrawal by every route to a seat ─────────────────────────────────────

test('an approved officer change also withdraws the nominee\'s pending join request', function () {
    Notification::fake();
    $join = fileJoin($this->student, $this->itGuild);   // asked to join a DIFFERENT org
    $request = app(RequestOfficerChange::class)->execute($this->president, OfficerPosition::Secretary, $this->student);

    app(ApproveOfficerChange::class)->execute($this->sdaoA, $request);

    expect($join->fresh()->status)->toBe(JoinRequestStatus::Withdrawn);
    expect($join->fresh()->decision_comment)->toContain('became an officer of another organization');
    Notification::assertSentTo($this->student, JoinRequestWithdrawnNotification::class);
});

test('founding an organization withdraws the founder\'s pending join request', function () {
    Notification::fake();
    $founder = User::factory()->create(['account_status' => 'verified']);
    $join = fileJoin($founder, $this->itGuild);

    $newOrg = Organization::create(['name' => 'Founders Guild', 'school_id' => $this->org->school_id, 'program_id' => $this->org->program_id]);
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration, 'organization_id' => $newOrg->id,
        'status' => DocumentStatus::Draft, 'submitted_by' => $founder->id,
    ]);
    OrganizationRegistrationDetail::factory()->create(['document_id' => $doc->id, 'organization_type' => OrganizationType::CoCurricular]);
    app(ApprovalEngine::class)->submit($doc, $founder);
    $doc->refresh();
    app(ApprovalEngine::class)->approve($doc, $this->sdaoA);

    app(ApproveOrganizationRegistration::class)->execute($doc->refresh(), $this->sdaoB);

    expect(OrganizationMembership::where('user_id', $founder->id)->active()->count())->toBe(1);
    expect($join->fresh()->status)->toBe(JoinRequestStatus::Withdrawn);
    Notification::assertSentTo($founder, JoinRequestWithdrawnNotification::class);
});

// ── Notification decision ───────────────────────────────────────────────────

test('the student is told when the request was for a DIFFERENT org, and nobody else is', function () {
    Notification::fake();
    fileJoin($this->student, $this->itGuild);
    Notification::fake(); // forget the "request received" notices to the IT Guild reviewers

    app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, $this->student, OfficerPosition::President);

    Notification::assertSentToTimes($this->student, JoinRequestWithdrawnNotification::class, 1);
    Notification::assertSentTo($this->student, OfficerSeatGrantedNotification::class);
    // The other org's adviser and officers learn from the queue, not from a notification.
    Notification::assertNotSentTo($this->adviserTwo, JoinRequestWithdrawnNotification::class);
    Notification::assertNotSentTo(User::where('email', 'student-beta@students.nu-lipa.edu.ph')->firstOrFail(), JoinRequestWithdrawnNotification::class);
});

test('no second notice when the request was for the very org that just bound them', function () {
    Notification::fake();
    fileJoin($this->student, $this->org);
    Notification::fake();

    app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, $this->student, OfficerPosition::President);

    Notification::assertNotSentTo($this->student, JoinRequestWithdrawnNotification::class);
    Notification::assertSentTo($this->student, OfficerSeatGrantedNotification::class);
});

test('the withdrawn notice says nothing about which org the student joined, in any visible text', function () {
    $join = fileJoin($this->student, $this->itGuild);
    $notification = new JoinRequestWithdrawnNotification($join);

    $mail = $notification->toMail($this->student);
    $everything = implode("\n", [$mail->envelope()->subject, strip_tags($mail->render()), implode("\n", array_filter($notification->toArray($this->student), 'is_scalar'))]);

    expect($notification->via($this->student))->toBe(['mail', 'database']);
    expect($everything)->toContain('IT Guild')->not->toContain('Computing Society');
});

// ── Adviser's queue ─────────────────────────────────────────────────────────

test('the adviser\'s queue lists what the system closed, with the reason, for 14 days — and only for their own org', function () {
    $this->withoutVite();
    $join = fileJoin($this->student, $this->org);
    $elsewhere = fileJoin(User::factory()->create(['account_status' => 'verified']), $this->itGuild);
    app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, $this->student, OfficerPosition::President);
    $elsewhere->update(['status' => JoinRequestStatus::Withdrawn, 'decided_at' => now(), 'decision_comment' => 'x']);

    $this->actingAs($this->adviser)->get(route('review.join-requests.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('queue', 0)
            ->has('closed', 1)
            ->where('closed.0.id', $join->id)
            ->where('closed.0.student.name', $this->student->name)
            ->where('closed.0.reason', fn ($r) => str_contains($r, 'became an officer')));

    // Older than the window: drops off.
    $join->update(['decided_at' => now()->subDays(15)]);
    $this->actingAs($this->adviser)->get(route('review.join-requests.index'))
        ->assertInertia(fn ($page) => $page->has('closed', 0));
});

test('the reason shown to reviewers never names another organization', function () {
    $join = fileJoin($this->student, $this->itGuild);
    app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, $this->student, OfficerPosition::President);

    expect($join->fresh()->decision_comment)->toBe('Withdrawn automatically: the student became an officer of another organization.');
    expect($join->fresh()->decision_comment)->not->toContain('Computing Society');
});
