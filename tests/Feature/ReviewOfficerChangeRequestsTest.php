<?php

use App\Enums\OfficerChangeRequestStatus;
use App\Enums\OfficerPosition;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\OfficerChangeApprovedNotification;
use App\Notifications\OfficerChangeDeclinedNotification;
use App\Organizations\Admin\ApproveOfficerChange;
use App\Organizations\Admin\DeclineOfficerChange;
use App\Organizations\BindOrganizationOfficer;
use App\Organizations\RequestOfficerChange;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * Covers App\Organizations\Admin\ApproveOfficerChange /
 * DeclineOfficerChange — the SDAO-admin-only finalize step for a request
 * filed via App\Organizations\RequestOfficerChange. Mirrors
 * ReviewJoinRequestsTest's structure.
 */
beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->requestAction = app(RequestOfficerChange::class);
    $this->approveAction = app(ApproveOfficerChange::class);
    $this->declineAction = app(DeclineOfficerChange::class);
    $this->bindAction = app(BindOrganizationOfficer::class);

    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail(); // President
    $this->studentDelta = User::where('email', 'student-delta@students.nu-lipa.edu.ph')->firstOrFail(); // Secretary
    $this->adviserOne = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->adviserTwo = User::where('email', 'adviser-two@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();

    Notification::fake();
});

test('an SDAO admin can approve a request — the swap happens with matching ended_at/started_at', function () {
    $nominee = User::factory()->create(['account_status' => 'verified']);
    $changeRequest = $this->requestAction->execute($this->studentAlpha, OfficerPosition::Secretary, $nominee);

    $newMembership = $this->approveAction->execute($this->sdaoA, $changeRequest);

    expect($newMembership->is_active)->toBeTrue();
    expect($newMembership->user_id)->toBe($nominee->id);
    expect($newMembership->started_at)->not->toBeNull();

    $oldMembership = OrganizationMembership::where('user_id', $this->studentDelta->id)
        ->where('organization_id', $this->org->id)
        ->where('position', OfficerPosition::Secretary->value)
        ->firstOrFail();
    expect($oldMembership->is_active)->toBeFalse();
    expect($oldMembership->ended_at->equalTo($newMembership->started_at))->toBeTrue();

    expect(OrganizationMembership::where('organization_id', $this->org->id)
        ->where('position', OfficerPosition::Secretary->value)
        ->where('is_active', true)
        ->count())->toBe(1);

    $changeRequest->refresh();
    expect($changeRequest->status)->toBe(OfficerChangeRequestStatus::Approved);
    expect($changeRequest->decided_by)->toBe($this->sdaoA->id);
    expect($changeRequest->decided_at)->not->toBeNull();

    Notification::assertSentTo([$this->studentAlpha, $nominee], OfficerChangeApprovedNotification::class);
});

test('approving a request that promotes a sitting officer into a NEW seat auto-closes their other seat in the same org', function () {
    // Student Delta is Secretary; promote them to President.
    $changeRequest = $this->requestAction->execute($this->studentDelta, OfficerPosition::President, $this->studentDelta);

    // studentDelta nominating themself for President while holding Secretary
    // is legal — guard #4/#5 in RequestOfficerChange only block the SAME
    // seat and membership-elsewhere, not a different seat in the same org.
    $newPresidentMembership = $this->approveAction->execute($this->sdaoA, $changeRequest);

    expect($newPresidentMembership->position)->toBe(OfficerPosition::President);
    expect($newPresidentMembership->is_active)->toBeTrue();

    $oldSecretaryMembership = OrganizationMembership::where('user_id', $this->studentDelta->id)
        ->where('organization_id', $this->org->id)
        ->where('position', OfficerPosition::Secretary->value)
        ->firstOrFail();
    expect($oldSecretaryMembership->is_active)->toBeFalse();
    expect($oldSecretaryMembership->ended_at)->not->toBeNull();

    // Delta now holds exactly one active seat in this org.
    expect(OrganizationMembership::where('user_id', $this->studentDelta->id)
        ->where('organization_id', $this->org->id)
        ->where('is_active', true)
        ->count())->toBe(1);
});

test('non-SDAO users are forbidden from finalizing, at both the action and the route', function () {
    $nominee = User::factory()->create(['account_status' => 'verified']);
    $changeRequest = $this->requestAction->execute($this->studentAlpha, OfficerPosition::Secretary, $nominee);

    foreach ([$this->adviserOne, $this->studentAlpha, User::factory()->create()] as $nonAdmin) {
        expect(fn () => $this->approveAction->execute($nonAdmin, $changeRequest))
            ->toThrow(AuthorizationException::class);

        $this->actingAs($nonAdmin)->post(route('admin.officer-change-requests.approve', $changeRequest))
            ->assertForbidden();
        $this->actingAs($nonAdmin)->post(route('admin.officer-change-requests.decline', $changeRequest))
            ->assertForbidden();
    }

    $changeRequest->refresh();
    expect($changeRequest->status)->toBe(OfficerChangeRequestStatus::Pending);
});

test('an already-decided request cannot be decided again', function () {
    $nominee = User::factory()->create(['account_status' => 'verified']);
    $changeRequest = $this->requestAction->execute($this->studentAlpha, OfficerPosition::Secretary, $nominee);
    $this->approveAction->execute($this->sdaoA, $changeRequest);

    expect(fn () => $this->approveAction->execute($this->sdaoA, $changeRequest))->toThrow(ValidationException::class);
    expect(fn () => $this->declineAction->execute($this->sdaoA, $changeRequest))->toThrow(ValidationException::class);
});

test('race: the nominee is no longer verified by the time SDAO reviews — approve is blocked, request stays Pending', function () {
    $nominee = User::factory()->create(['account_status' => 'verified']);
    $changeRequest = $this->requestAction->execute($this->studentAlpha, OfficerPosition::Secretary, $nominee);

    $nominee->update(['account_status' => 'rejected']);

    expect(fn () => $this->approveAction->execute($this->sdaoA, $changeRequest))->toThrow(ValidationException::class);
    expect($changeRequest->fresh()->status)->toBe(OfficerChangeRequestStatus::Pending);
    expect(OrganizationMembership::where('user_id', $nominee->id)->exists())->toBeFalse();
});

test('race: the nominee is bound to a different org by the time SDAO reviews — approve is blocked', function () {
    $itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $nominee = User::factory()->create(['account_status' => 'verified']);
    $changeRequest = $this->requestAction->execute($this->studentAlpha, OfficerPosition::Secretary, $nominee);

    $this->bindAction->execute($this->adviserTwo, $itGuild, $nominee, OfficerPosition::Secretary);

    expect(fn () => $this->approveAction->execute($this->sdaoA, $changeRequest))->toThrow(ValidationException::class);
    expect($changeRequest->fresh()->status)->toBe(OfficerChangeRequestStatus::Pending);
});

test('race: the adviser makes the exact same swap directly before SDAO reviews — approve is blocked with "already holds"', function () {
    $nominee = User::factory()->create(['account_status' => 'verified']);
    $changeRequest = $this->requestAction->execute($this->studentAlpha, OfficerPosition::Secretary, $nominee);

    $this->bindAction->execute($this->adviserOne, $this->org, $nominee, OfficerPosition::Secretary);

    expect(fn () => $this->approveAction->execute($this->sdaoA, $changeRequest))->toThrow(ValidationException::class);
    expect($changeRequest->fresh()->status)->toBe(OfficerChangeRequestStatus::Pending);
});

test('race: the adviser swaps the seat to a DIFFERENT person before SDAO reviews — approve still succeeds against the live holder', function () {
    $nominee = User::factory()->create(['account_status' => 'verified']);
    $changeRequest = $this->requestAction->execute($this->studentAlpha, OfficerPosition::Secretary, $nominee);

    $interveningHolder = User::factory()->create(['account_status' => 'verified']);
    $this->bindAction->execute($this->adviserOne, $this->org, $interveningHolder, OfficerPosition::Secretary);

    $newMembership = $this->approveAction->execute($this->sdaoA, $changeRequest);

    expect($newMembership->user_id)->toBe($nominee->id);

    $interveningMembership = OrganizationMembership::where('user_id', $interveningHolder->id)
        ->where('organization_id', $this->org->id)
        ->firstOrFail();
    expect($interveningMembership->is_active)->toBeFalse();
    expect($interveningMembership->ended_at)->not->toBeNull();
});

test('decline is terminal, stores a comment, creates no membership, and notifies the requester', function () {
    $nominee = User::factory()->create(['account_status' => 'verified']);
    $changeRequest = $this->requestAction->execute($this->studentAlpha, OfficerPosition::Secretary, $nominee);

    $this->declineAction->execute($this->sdaoA, $changeRequest, 'Not the right fit.');

    $changeRequest->refresh();
    expect($changeRequest->status)->toBe(OfficerChangeRequestStatus::Declined);
    expect($changeRequest->decision_comment)->toBe('Not the right fit.');
    expect(OrganizationMembership::where('user_id', $nominee->id)->exists())->toBeFalse();

    Notification::assertSentTo($this->studentAlpha, OfficerChangeDeclinedNotification::class);
});

test('HTTP: SDAO approve redirects to the queue with a flash message', function () {
    $nominee = User::factory()->create(['account_status' => 'verified']);
    $changeRequest = $this->requestAction->execute($this->studentAlpha, OfficerPosition::Secretary, $nominee);

    $response = $this->actingAs($this->sdaoA)->post(route('admin.officer-change-requests.approve', $changeRequest));

    $response->assertRedirect(route('admin.officer-change-requests.index'));
    expect($changeRequest->fresh()->status)->toBe(OfficerChangeRequestStatus::Approved);
});

test('Inertia: the queue page lists pending requests and flags a stale one', function () {
    $nominee = User::factory()->create(['account_status' => 'verified']);
    $changeRequest = $this->requestAction->execute($this->studentAlpha, OfficerPosition::Secretary, $nominee);

    // Make it stale: adviser rebinds the seat directly.
    $interveningHolder = User::factory()->create(['account_status' => 'verified']);
    $this->bindAction->execute($this->adviserOne, $this->org, $interveningHolder, OfficerPosition::Secretary);

    $response = $this->actingAs($this->sdaoA)->withoutVite()->get(route('admin.officer-change-requests.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('admin/officer-change-requests/index')
        ->has('requests', 1)
        ->where('requests.0.id', $changeRequest->id)
        ->where('requests.0.is_stale', true));
});
