<?php

use App\Enums\OfficerChangeRequestStatus;
use App\Enums\OfficerPosition;
use App\Models\OfficerChangeRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\OfficerChangeRequestedNotification;
use App\Organizations\BindOrganizationOfficer;
use App\Organizations\EligibleOfficerCandidates;
use App\Organizations\RequestOfficerChange;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * Covers App\Organizations\RequestOfficerChange — the officer-facing,
 * strictly additive alternative to the adviser's direct
 * BindOrganizationOfficer path (unchanged). Filing a request never touches
 * organization_memberships; only an SDAO admin finalizing it does (see
 * ReviewOfficerChangeRequestsTest).
 */
beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->action = app(RequestOfficerChange::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail(); // President, Computing Society
    $this->studentDelta = User::where('email', 'student-delta@students.nu-lipa.edu.ph')->firstOrFail(); // Secretary, Computing Society
    $this->studentBeta = User::where('email', 'student-beta@students.nu-lipa.edu.ph')->firstOrFail(); // President, IT Guild
    $this->adviserOne = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
    Notification::fake();
});

test('a sitting president can file a request naming a new nominee for a different position', function () {
    $nominee = User::factory()->create(['account_status' => 'verified']);

    $changeRequest = $this->action->execute($this->studentAlpha, OfficerPosition::Secretary, $nominee, 'Delta is graduating.');

    expect($changeRequest->status)->toBe(OfficerChangeRequestStatus::Pending);
    expect($changeRequest->organization_id)->toBe($this->org->id);
    expect($changeRequest->requested_by)->toBe($this->studentAlpha->id);
    expect($changeRequest->nominee_id)->toBe($nominee->id);
    expect($changeRequest->outgoing_user_id)->toBe($this->studentDelta->id);
    expect($changeRequest->reason)->toBe('Delta is graduating.');

    Notification::assertSentTo([$this->sdaoA, $this->sdaoB], OfficerChangeRequestedNotification::class);
});

test('a sitting secretary can file a request too — equal partners', function () {
    $nominee = User::factory()->create(['account_status' => 'verified']);

    $changeRequest = $this->action->execute($this->studentDelta, OfficerPosition::President, $nominee);

    expect($changeRequest->status)->toBe(OfficerChangeRequestStatus::Pending);
    expect($changeRequest->requested_by)->toBe($this->studentDelta->id);
});

test('filing a request for a vacant position stores a null outgoing_user_id', function () {
    $nominee = User::factory()->create(['account_status' => 'verified']);

    $changeRequest = $this->action->execute($this->studentBeta, OfficerPosition::Secretary, $nominee);

    expect($changeRequest->outgoing_user_id)->toBeNull();
});

test('a non-officer cannot file a request', function () {
    $stranger = User::factory()->create();
    $nominee = User::factory()->create(['account_status' => 'verified']);

    expect(fn () => $this->action->execute($stranger, OfficerPosition::Secretary, $nominee))
        ->toThrow(AuthorizationException::class);
});

test('an adviser (not an officer) cannot file a request', function () {
    $nominee = User::factory()->create(['account_status' => 'verified']);

    expect(fn () => $this->action->execute($this->adviserOne, OfficerPosition::Secretary, $nominee))
        ->toThrow(AuthorizationException::class);
});

test('a second pending request for the SAME seat is rejected', function () {
    $nominee1 = User::factory()->create(['account_status' => 'verified']);
    $nominee2 = User::factory()->create(['account_status' => 'verified']);

    $this->action->execute($this->studentAlpha, OfficerPosition::Secretary, $nominee1);

    expect(fn () => $this->action->execute($this->studentAlpha, OfficerPosition::Secretary, $nominee2))
        ->toThrow(ValidationException::class);
});

test('a pending request for the OTHER seat in the same org is allowed', function () {
    $secretaryNominee = User::factory()->create(['account_status' => 'verified']);
    $presidentNominee = User::factory()->create(['account_status' => 'verified']);

    $this->action->execute($this->studentAlpha, OfficerPosition::Secretary, $secretaryNominee);
    $second = $this->action->execute($this->studentDelta, OfficerPosition::President, $presidentNominee);

    expect($second->status)->toBe(OfficerChangeRequestStatus::Pending);
    expect(OfficerChangeRequest::where('organization_id', $this->org->id)->pending()->count())->toBe(2);
});

test('nominating an unverified student is rejected', function () {
    $unverified = User::factory()->create(['account_status' => 'unverified']);

    expect(fn () => $this->action->execute($this->studentAlpha, OfficerPosition::Secretary, $unverified))
        ->toThrow(ValidationException::class);
});

test('nominating a student already active elsewhere is rejected', function () {
    expect(fn () => $this->action->execute($this->studentAlpha, OfficerPosition::President, $this->studentBeta))
        ->toThrow(ValidationException::class);
});

test('nominating the current holder of the same seat is rejected', function () {
    expect(fn () => $this->action->execute($this->studentAlpha, OfficerPosition::Secretary, $this->studentDelta))
        ->toThrow(ValidationException::class);
});

test('nominating an account holding an approver role is rejected', function () {
    expect(fn () => $this->action->execute($this->studentAlpha, OfficerPosition::Secretary, $this->adviserOne))
        ->toThrow(ValidationException::class);
});

test('filing a request mutates no organization_memberships row', function () {
    $nominee = User::factory()->create(['account_status' => 'verified']);
    $countBefore = OrganizationMembership::count();

    $this->action->execute($this->studentAlpha, OfficerPosition::Secretary, $nominee);

    expect(OrganizationMembership::count())->toBe($countBefore);
    expect(OrganizationMembership::where('user_id', $nominee->id)->exists())->toBeFalse();
});

test('the adviser can still bind directly via Manage Officers while a request is pending for the same seat', function () {
    $nominee = User::factory()->create(['account_status' => 'verified']);
    $this->action->execute($this->studentAlpha, OfficerPosition::Secretary, $nominee);

    $directNominee = User::factory()->create(['account_status' => 'verified']);
    $membership = app(BindOrganizationOfficer::class)->execute(
        $this->adviserOne, $this->org, $directNominee, OfficerPosition::Secretary,
    );

    expect($membership->is_active)->toBeTrue();
});

test('the create page renders for an active officer', function () {
    $response = $this->actingAs($this->studentAlpha)->withoutVite()->get(route('organizations.officer-change.create'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('organizations/officer-change/create')
        ->where('organization.name', 'Computing Society'));
});

test('the create page renders a null organization for a non-officer, not a 403', function () {
    $stranger = User::factory()->create();

    $response = $this->actingAs($stranger)->withoutVite()->get(route('organizations.officer-change.create'));

    $response->assertOk()->assertInertia(fn ($page) => $page->where('organization', null));
});

test('the nominee search endpoint returns the same eligible set EligibleOfficerCandidates computes directly', function () {
    $response = $this->actingAs($this->studentAlpha)->getJson(route('organizations.officer-change.search', ['q' => '']));

    $response->assertOk();
    $searchIds = collect($response->json('students'))->pluck('id')->sort()->values()->all();

    $expectedIds = app(EligibleOfficerCandidates::class)
        ->query($this->org)
        ->limit(20)
        ->get(['id'])
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    expect($searchIds)->toBe($expectedIds);
});
