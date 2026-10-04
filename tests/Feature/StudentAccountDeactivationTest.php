<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\JoinRequestStatus;
use App\Enums\OfficerChangeRequestStatus;
use App\Enums\OfficerPosition;
use App\Enums\OfficerSeatEndReason;
use App\Enums\OrganizationType;
use App\Enums\TransitionAction;
use App\Identity\Admin\AccountDeactivator;
use App\Identity\Admin\DeactivateAccount;
use App\Identity\Admin\ReactivateAccount;
use App\Models\Document;
use App\Models\OfficerChangeRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use App\Notifications\OfficerAccountDeactivatedNotification;
use App\Notifications\OfficerChangeRequestClosedNotification;
use App\Notifications\OfficerSeatEndedNotification;
use App\Organizations\Admin\ApproveOfficerChange;
use App\Organizations\ApproveJoinRequest;
use App\Organizations\BindOrganizationOfficer;
use App\Organizations\EligibleOfficerCandidates;
use App\Organizations\OrganizationDetailData;
use App\Organizations\RequestOfficerChange;
use App\Organizations\RequestToJoinOrganization;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Deactivating a STUDENT account. The officer seat ends with it, immediately
 * and atomically; nothing is refused because someone holds a seat.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    Notification::fake();
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->sdao = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->president = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->secretary = User::where('email', 'student-delta@students.nu-lipa.edu.ph')->firstOrFail();
});

function deactivateStudent(User $actor, User $student, ?string $reason = null): void
{
    app(DeactivateAccount::class)->execute($actor, $student, $reason);
}

// ── The seat ends with the account ──────────────────────────────────────────

test('deactivating a student officer ends their seat immediately and keeps it as history', function () {
    deactivateStudent($this->sdao, $this->president, 'Graduated.');

    expect($this->president->fresh()->isDeactivated())->toBeTrue();
    $seat = OrganizationMembership::where('user_id', $this->president->id)->firstOrFail();
    expect($seat->is_active)->toBeFalse();
    expect($seat->ended_at)->not->toBeNull();
    expect(OrganizationMembership::where('user_id', $this->president->id)->count())->toBe(1);   // kept, never deleted
    // The other officer is untouched.
    expect(OrganizationMembership::where('user_id', $this->secretary->id)->active()->exists())->toBeTrue();
});

test('their pending officer change requests and join requests are withdrawn', function () {
    $request = app(RequestOfficerChange::class)->execute($this->president, OfficerPosition::Secretary, User::factory()->create(['account_status' => 'verified']));

    deactivateStudent($this->sdao, $this->president);
    expect($request->fresh()->status)->toBe(OfficerChangeRequestStatus::Withdrawn);

    // A seatless student's pending join request is withdrawn too.
    $joiner = User::factory()->create(['account_status' => 'verified']);
    $join = app(RequestToJoinOrganization::class)->execute($joiner, $this->org);

    deactivateStudent($this->sdao, $joiner);

    expect($join->fresh()->status)->toBe(JoinRequestStatus::Withdrawn);
    expect($join->fresh()->decision_comment)->toContain('account was deactivated');
});

test('the SDAO reason and actor are recorded, and every session is ended', function () {
    deactivateStudent($this->sdao, $this->president, 'Left the university.');

    $fresh = $this->president->fresh();
    expect($fresh->deactivated_reason)->toBe('Left the university.');
    expect($fresh->deactivated_by)->toBe($this->sdao->id);
});

test('an org left with no active officers is allowed, not refused', function () {
    deactivateStudent($this->sdao, $this->president);
    deactivateStudent($this->sdao, $this->secretary);

    expect(OrganizationMembership::where('organization_id', $this->org->id)->active()->count())->toBe(0);
    expect($this->secretary->fresh()->isDeactivated())->toBeTrue();
});

// ── Documents ───────────────────────────────────────────────────────────────

test('documents the officer submitted stay with the org — the remaining officer can still act on them', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration, 'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft, 'submitted_by' => $this->president->id,
    ]);
    OrganizationRegistrationDetail::factory()->create(['document_id' => $doc->id, 'organization_type' => OrganizationType::CoCurricular]);
    app(ApprovalEngine::class)->submit($doc, $this->president);
    $doc->refresh();

    deactivateStudent($this->sdao, $this->president);

    expect($doc->fresh()->status)->toBe(DocumentStatus::InReview);   // not withdrawn
    expect(Gate::forUser($this->secretary)->allows('view', $doc))->toBeTrue();
    expect(Gate::forUser($this->president->fresh())->allows('view', $doc))->toBeFalse();
});

test('an in-flight founding registration is withdrawn rather than stranded', function () {
    $founder = User::factory()->create(['account_status' => 'verified']);
    $newOrg = Organization::create(['name' => 'Founders Guild', 'school_id' => $this->org->school_id, 'program_id' => $this->org->program_id]);
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration, 'organization_id' => $newOrg->id,
        'status' => DocumentStatus::Draft, 'submitted_by' => $founder->id,
    ]);
    OrganizationRegistrationDetail::factory()->create(['document_id' => $doc->id, 'organization_type' => OrganizationType::CoCurricular]);
    app(ApprovalEngine::class)->submit($doc, $founder);

    deactivateStudent($this->sdao, $founder);

    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::Rejected);
    $transition = $doc->transitions()->where('action', TransitionAction::Withdrawn->value)->firstOrFail();
    expect($transition->comment ?? $transition->reason ?? '')->toContain('account was deactivated');
});

// ── Notices ─────────────────────────────────────────────────────────────────

test('the deactivated student is mailed — mail only, no replacement named', function () {
    deactivateStudent($this->sdao, $this->president);

    $captured = null;
    Notification::assertSentTo($this->president, OfficerSeatEndedNotification::class, function ($n) use (&$captured) {
        $captured = $n;

        return $n->reason === OfficerSeatEndReason::AccountDeactivated && $n->position === OfficerPosition::President;
    });

    expect($captured->via($this->president))->toBe(['mail']);   // the bell is unreachable once deactivated
    $mail = $captured->toMail($this->president);
    $text = $mail->envelope()->subject."\n".strip_tags($mail->render());
    expect($text)->toContain('can no longer sign in')->toContain('contact SDAO')->toContain('Computing Society');
    expect($text)->not->toContain('Student Delta')->not->toContain('replac');
});

test('the adviser is told which seat emptied and how many active officers remain', function () {
    deactivateStudent($this->sdao, $this->president);

    Notification::assertSentTo($this->adviser, OfficerAccountDeactivatedNotification::class, fn ($n) => $n->position === OfficerPosition::President && $n->remainingOfficers === 1 && $n->officerName === $this->president->name);

    deactivateStudent($this->sdao, $this->secretary);

    Notification::assertSentTo($this->adviser, OfficerAccountDeactivatedNotification::class, fn ($n) => $n->remainingOfficers === 0);
});

test('the adviser notice says plainly when nobody is left', function () {
    $n = new OfficerAccountDeactivatedNotification($this->org, 'Pat Example', OfficerPosition::President, 0);

    expect($n->toArray($this->adviser)['body'])->toContain('no active officers');
    expect(strip_tags($n->toMail($this->adviser)->render()))->toContain('now has no active officers');
    expect((new OfficerAccountDeactivatedNotification($this->org, 'Pat Example', OfficerPosition::President, 1))->toArray($this->adviser)['body'])->toContain('One active officer remains');
});

test('a student with no seat triggers no seat notices', function () {
    $plain = User::factory()->create(['account_status' => 'verified']);

    deactivateStudent($this->sdao, $plain);

    expect($plain->fresh()->isDeactivated())->toBeTrue();
    Notification::assertNothingSent();
});

// ── Reactivation, eligibility ───────────────────────────────────────────────

test('reactivating does not restore the seat — the adviser can bind them again', function () {
    deactivateStudent($this->sdao, $this->president);
    app(ReactivateAccount::class)->execute($this->sdao, $this->president->fresh());

    $student = $this->president->fresh();
    expect($student->isDeactivated())->toBeFalse();
    expect(OrganizationMembership::where('user_id', $student->id)->active()->exists())->toBeFalse();

    expect(app(EligibleOfficerCandidates::class)->matches($this->org, $student))->toBeTrue();
    $membership = app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, $student, OfficerPosition::President);
    expect($membership->is_active)->toBeTrue();
});

test('a deactivated student can no longer be nominated, bound or approved as an officer', function () {
    $ghost = User::factory()->create(['account_status' => 'verified']);
    $join = app(RequestToJoinOrganization::class)->execute($ghost, $this->org);
    $join->update(['status' => JoinRequestStatus::Pending]);
    $ghost->forceFill(['deactivated_at' => now()])->save();

    expect(app(EligibleOfficerCandidates::class)->matches($this->org, $ghost->fresh()))->toBeFalse();
    expect(fn () => app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, $ghost->fresh(), OfficerPosition::Secretary))
        ->toThrow(ValidationException::class);
    $this->secretary->organizationMemberships()->active()->update(['is_active' => false, 'ended_at' => now()]);
    expect(fn () => app(ApproveJoinRequest::class)->execute($this->adviser, $join->fresh(), OfficerPosition::Secretary))
        ->toThrow(ValidationException::class, 'has been deactivated');
});

// ── Guards and atomicity ────────────────────────────────────────────────────

test('only SDAO can do it, never to themselves, and not twice', function () {
    expect(fn () => deactivateStudent($this->adviser, $this->president))->toThrow(AuthorizationException::class);
    expect(fn () => deactivateStudent($this->sdao, $this->sdao))->toThrow(ValidationException::class);

    deactivateStudent($this->sdao, $this->president);
    expect(fn () => deactivateStudent($this->sdao, $this->president->fresh()))->toThrow(ValidationException::class, 'already deactivated');
});

test('it is all-or-nothing: if closing the account fails, the seat and requests are untouched', function () {
    $request = app(RequestOfficerChange::class)->execute($this->president, OfficerPosition::Secretary, User::factory()->create(['account_status' => 'verified']));
    Notification::fake(); // forget the "request filed" notice to SDAO
    app()->instance(AccountDeactivator::class, new class extends AccountDeactivator
    {
        public function deactivate(User $account, ?User $by = null, ?string $reason = null): void
        {
            throw new RuntimeException('boom');
        }
    });

    expect(fn () => app(DeactivateAccount::class)->execute($this->sdao, $this->president, null))->toThrow(RuntimeException::class);

    expect($this->president->fresh()->isDeactivated())->toBeFalse();
    expect(OrganizationMembership::where('user_id', $this->president->id)->active()->exists())->toBeTrue();
    expect($request->fresh()->status)->toBe(OfficerChangeRequestStatus::Pending);
    Notification::assertNothingSent();
});

// ── HTTP and page data ──────────────────────────────────────────────────────

test('over HTTP the toast says the seat ended and the adviser was told', function () {
    $response = $this->actingAs($this->sdao)->post(route('admin.accounts.deactivate', $this->president), ['reason' => 'Left.']);

    $response->assertRedirect();
    $toast = session('flash');
    expect(json_encode($toast))->toContain('Account deactivated')->toContain('President seat of Computing Society ended')->toContain('adviser was told');
});

test('the organization page tells the dialog how many officers would remain', function () {
    $rows = collect(app(OrganizationDetailData::class)->officers($this->org));

    expect($rows)->toHaveCount(2);
    expect($rows->pluck('remaining_officers')->all())->toBe([1, 1]);
    expect($rows->pluck('user_id')->sort()->values()->all())->toBe(collect([$this->president->id, $this->secretary->id])->sort()->values()->all());

    deactivateStudent($this->sdao, $this->secretary);

    expect(collect(app(OrganizationDetailData::class)->officers($this->org))->pluck('remaining_officers')->all())->toBe([0]);
});

test('Manage Officers still only ends the SEAT — the account stays active', function () {
    $membership = OrganizationMembership::where('user_id', $this->secretary->id)->active()->firstOrFail();

    $this->actingAs($this->adviser)->delete(route('officers.destroy', ['organization' => $this->org->id, 'membership' => $membership->id]))->assertRedirect();

    expect($this->secretary->fresh()->isDeactivated())->toBeFalse();
    expect(OrganizationMembership::where('user_id', $this->secretary->id)->active()->exists())->toBeFalse();
});

// ── A request that NAMES the deactivated student ────────────────────────────

test('REGRESSION: an officer files a request naming a student, SDAO deactivates that student, the request is withdrawn and the queue is clean', function () {
    $this->withoutVite();
    $nominee = User::factory()->create(['account_status' => 'verified']);
    $request = app(RequestOfficerChange::class)->execute($this->president, OfficerPosition::Secretary, $nominee);

    $this->actingAs($this->sdao)->get(route('admin.officer-change-requests.index'))
        ->assertInertia(fn ($page) => $page->has('requests', 1));

    Notification::fake();
    deactivateStudent($this->sdao, $nominee);

    $request->refresh();
    expect($request->status)->toBe(OfficerChangeRequestStatus::Withdrawn);
    expect($request->decided_at)->not->toBeNull();
    expect($request->decided_by)->toBeNull();
    // The recorded reason says the nominee can no longer be considered — nothing about why.
    expect($request->decision_comment)->toBe('Withdrawn automatically: the nominee can no longer be considered for this seat.');
    expect(strtolower($request->decision_comment))->not->toContain('deactivat');

    // The queue is clean, and SDAO cannot act on it any more.
    $this->actingAs($this->sdao)->get(route('admin.officer-change-requests.index'))
        ->assertInertia(fn ($page) => $page->has('requests', 0));
    expect(fn () => app(ApproveOfficerChange::class)->execute($this->sdao, $request))->toThrow(ValidationException::class, 'already been decided');

    // The pending-per-seat slot is free again for a new request.
    expect(app(RequestOfficerChange::class)->execute($this->president, OfficerPosition::Secretary, User::factory()->create(['account_status' => 'verified']))->status)
        ->toBe(OfficerChangeRequestStatus::Pending);
});

test('the requester, still a sitting officer, is told it was closed and can file again — and the notice never says why', function () {
    $nominee = User::factory()->create(['account_status' => 'verified']);
    app(RequestOfficerChange::class)->execute($this->president, OfficerPosition::Secretary, $nominee);
    Notification::fake();

    deactivateStudent($this->sdao, $nominee, 'Confidential reason.');

    $captured = null;
    Notification::assertSentTo($this->president, OfficerChangeRequestClosedNotification::class, function ($n) use (&$captured) {
        $captured = $n;

        return true;
    });
    expect($captured->via($this->president))->toBe(['mail', 'database']);

    $mail = $captured->toMail($this->president);
    $everything = strtolower(implode("\n", [$mail->envelope()->subject, strip_tags($mail->render()), implode("\n", array_filter($captured->toArray($this->president), 'is_scalar'))]));
    expect($everything)->toContain('can no longer be considered')->toContain('file a new request');
    expect($everything)->not->toContain('deactivat')->not->toContain('confidential');

    // Nobody else hears about it: not the nominee, not the other officer, not SDAO.
    Notification::assertNotSentTo($nominee, OfficerChangeRequestClosedNotification::class);
    Notification::assertNotSentTo($this->secretary, OfficerChangeRequestClosedNotification::class);
    Notification::assertNotSentTo($this->sdao, OfficerChangeRequestClosedNotification::class);
});

test('a requester who no longer holds a seat is not told, and a request filed BY the deactivated student is closed quietly', function () {
    $nominee = User::factory()->create(['account_status' => 'verified']);
    $byGone = app(RequestOfficerChange::class)->execute($this->president, OfficerPosition::Secretary, $nominee);
    // The requester loses their seat first (this already withdraws their own request).
    app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, User::factory()->create(['account_status' => 'verified']), OfficerPosition::President);
    expect($byGone->fresh()->status)->toBe(OfficerChangeRequestStatus::Withdrawn);
    Notification::fake();

    // A request filed by the student being deactivated, naming themselves.
    $selfNaming = OfficerChangeRequest::factory()->create([
        'organization_id' => $this->org->id, 'requested_by' => $this->secretary->id,
        'position' => OfficerPosition::President, 'nominee_id' => $this->secretary->id,
        'status' => OfficerChangeRequestStatus::Pending,
    ]);
    deactivateStudent($this->sdao, $this->secretary);

    expect($selfNaming->fresh()->status)->toBe(OfficerChangeRequestStatus::Withdrawn);
    Notification::assertNotSentTo($this->secretary, OfficerChangeRequestClosedNotification::class);
});

// ── The toast's reverse action ──────────────────────────────────────────────

test('the deactivation toast offers "Reactivate account", and says that does not restore the seat', function () {
    $this->actingAs($this->sdao)->post(route('admin.accounts.deactivate', $this->president))->assertRedirect();

    $flash = session('flash');
    expect($flash['actions'][0])->toBe(['label' => 'Reactivate account', 'href' => route('admin.accounts.reactivate', $this->president), 'method' => 'post']);
    expect($flash['message'])->toContain('Reactivating restores the account only, not the seat.');
});

test('for a student with no seat the toast has no seat note, but the action is still labelled for what it does', function () {
    $plain = User::factory()->create(['account_status' => 'verified']);

    $this->actingAs($this->sdao)->post(route('admin.accounts.deactivate', $plain))->assertRedirect();

    $flash = session('flash');
    expect($flash['message'])->not->toContain('seat');
    expect($flash['actions'][0]['label'])->toBe('Reactivate account');
});

test('the reactivation really does restore only the account', function () {
    $this->actingAs($this->sdao)->post(route('admin.accounts.deactivate', $this->president));
    $this->actingAs($this->sdao)->post(route('admin.accounts.reactivate', $this->president))->assertRedirect();

    expect($this->president->fresh()->isDeactivated())->toBeFalse();
    expect(OrganizationMembership::where('user_id', $this->president->id)->active()->exists())->toBeFalse();
});
