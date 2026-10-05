<?php

use App\Enums\OfficerChangeRequestStatus;
use App\Enums\OfficerPosition;
use App\Models\OfficerChangeRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\OfficerChangeApprovedNotification;
use App\Notifications\OfficerChangeDeclinedNotification;
use App\Notifications\OfficerChangeRequestedNotification;
use App\Organizations\Admin\ApproveOfficerChange;
use App\Organizations\Admin\DeclineOfficerChange;
use App\Organizations\BindOrganizationOfficer;
use App\Organizations\RequestOfficerChange;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * A pending officer change request cannot outlive its requester's authority:
 * the moment the officer who filed it no longer holds a seat in the org, the
 * request is closed as Withdrawn (by the system — nobody reviewed it).
 */
beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    Notification::fake();
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->sdao = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->president = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->secretary = User::where('email', 'student-delta@students.nu-lipa.edu.ph')->firstOrFail();
});

function verifiedNominee(): User
{
    return User::factory()->create(['account_status' => 'verified']);
}

function fileRequest(User $requester, OfficerPosition $position): OfficerChangeRequest
{
    return app(RequestOfficerChange::class)->execute($requester, $position, verifiedNominee());
}

test('an adviser turnover that replaces the requester withdraws their pending request', function () {
    $request = fileRequest($this->president, OfficerPosition::Secretary);

    app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, verifiedNominee(), OfficerPosition::President);

    $request->refresh();
    expect($request->status)->toBe(OfficerChangeRequestStatus::Withdrawn);
    expect($request->decided_at)->not->toBeNull();
    expect($request->decided_by)->toBeNull();
    expect($request->decision_comment)->toContain('no longer holds a seat');
});

test('deactivating the requester through Manage Officers withdraws their pending request', function () {
    $request = fileRequest($this->president, OfficerPosition::Secretary);
    $membership = OrganizationMembership::where('user_id', $this->president->id)->active()->firstOrFail();

    $this->actingAs($this->adviser)
        ->delete(route('officers.destroy', ['organization' => $this->org->id, 'membership' => $membership->id]))
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(OfficerChangeRequestStatus::Withdrawn);
});

test('a withdrawn request vanishes from the SDAO queue, cannot be decided, and frees the seat for a new request', function () {
    $request = fileRequest($this->president, OfficerPosition::Secretary);
    app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, verifiedNominee(), OfficerPosition::President);

    $this->withoutVite()->actingAs($this->sdao)->get(route('admin.officer-change-requests.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('requests', 0));

    expect(fn () => app(ApproveOfficerChange::class)->execute($this->sdao, $request))
        ->toThrow(ValidationException::class, 'already been decided');
    expect(fn () => app(DeclineOfficerChange::class)->execute($this->sdao, $request))
        ->toThrow(ValidationException::class, 'already been decided');

    // The pending-per-seat slot is free again: the NEW president can file for the same seat.
    $newPresident = User::query()->whereHas('organizationMemberships', fn ($q) => $q->active()->where('position', 'president')->where('organization_id', $this->org->id))->firstOrFail();
    expect(fileRequest($newPresident, OfficerPosition::Secretary)->status)->toBe(OfficerChangeRequestStatus::Pending);
});

test('nothing is sent about the withdrawn request itself — no approved/declined notice to anyone', function () {
    $request = fileRequest($this->president, OfficerPosition::Secretary);
    Notification::fake(); // forget the "requested" notification to SDAO

    // The bind itself tells the replaced president their seat ended (see
    // OfficerSeatNotificationsTest); the withdrawal adds nothing on top.
    app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, verifiedNominee(), OfficerPosition::President);

    Notification::assertNotSentTo($this->president, OfficerChangeDeclinedNotification::class);
    Notification::assertNotSentTo($this->president, OfficerChangeApprovedNotification::class);
    Notification::assertNotSentTo($request->nominee, OfficerChangeApprovedNotification::class);
    Notification::assertNotSentTo($request->nominee, OfficerChangeDeclinedNotification::class);
    Notification::assertNotSentTo($this->sdao, OfficerChangeRequestedNotification::class);
});

test('an officer who is only moved to the other seat keeps their pending request', function () {
    $request = fileRequest($this->secretary, OfficerPosition::President);

    // Delta is promoted Secretary -> President by the adviser: still an officer here.
    app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, $this->secretary, OfficerPosition::President);

    expect(OrganizationMembership::where('user_id', $this->secretary->id)->active()->count())->toBe(1);
    expect($request->fresh()->status)->toBe(OfficerChangeRequestStatus::Pending);
});

test('only the removed officer\'s requests are withdrawn — the remaining officer\'s stay pending, as do other orgs\'', function () {
    $presidentsRequest = fileRequest($this->president, OfficerPosition::Secretary);
    $secretarysRequest = fileRequest($this->secretary, OfficerPosition::President);
    $itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $beta = User::where('email', 'student-beta@students.nu-lipa.edu.ph')->firstOrFail();
    $otherOrgRequest = app(RequestOfficerChange::class)->execute($beta, OfficerPosition::Secretary, verifiedNominee());

    app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, verifiedNominee(), OfficerPosition::President);

    expect($presidentsRequest->fresh()->status)->toBe(OfficerChangeRequestStatus::Withdrawn);
    expect($secretarysRequest->fresh()->status)->toBe(OfficerChangeRequestStatus::Pending);
    expect($otherOrgRequest->fresh()->status)->toBe(OfficerChangeRequestStatus::Pending);
    expect($otherOrgRequest->organization_id)->toBe($itGuild->id);
});

test('approving a request that replaces its own requester approves it (never withdraws it) and withdraws their other request', function () {
    // Alpha (President) asks to replace themselves AND, separately, the Secretary.
    $replaceSelf = fileRequest($this->president, OfficerPosition::President);
    $replaceSecretary = fileRequest($this->president, OfficerPosition::Secretary);

    app(ApproveOfficerChange::class)->execute($this->sdao, $replaceSelf);

    expect($replaceSelf->fresh()->status)->toBe(OfficerChangeRequestStatus::Approved);
    expect($replaceSecretary->fresh()->status)->toBe(OfficerChangeRequestStatus::Withdrawn);
});

test('a legacy pending request whose filer no longer holds a seat cannot be approved, only declined', function () {
    // Rows created before automatic withdrawal existed: filed by someone with no seat now.
    $orphan = OfficerChangeRequest::factory()->create([
        'organization_id' => $this->org->id,
        'requested_by' => verifiedNominee()->id,
        'position' => OfficerPosition::Secretary,
        'nominee_id' => verifiedNominee()->id,
        'status' => OfficerChangeRequestStatus::Pending,
    ]);

    expect(fn () => app(ApproveOfficerChange::class)->execute($this->sdao, $orphan))
        ->toThrow(ValidationException::class, 'no longer holds a seat');
    expect($orphan->fresh()->status)->toBe(OfficerChangeRequestStatus::Pending);
    expect(OrganizationMembership::where('user_id', $orphan->nominee_id)->exists())->toBeFalse();

    app(DeclineOfficerChange::class)->execute($this->sdao, $orphan, 'Filer left.');
    expect($orphan->fresh()->status)->toBe(OfficerChangeRequestStatus::Declined);
});
