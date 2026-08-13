<?php

use App\Enums\JoinRequestStatus;
use App\Enums\OfficerPosition;
use App\Models\Organization;
use App\Models\OrganizationJoinRequest;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\JoinRequestApprovedNotification;
use App\Notifications\JoinRequestDeclinedNotification;
use App\Organizations\ApproveJoinRequest;
use App\Organizations\DeclineJoinRequest;
use App\Organizations\RequestToJoinOrganization;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->requestAction = app(RequestToJoinOrganization::class);
    $this->approveAction = app(ApproveJoinRequest::class);
    $this->declineAction = app(DeclineJoinRequest::class);

    // Computing Society: BOTH officer slots filled (Student Alpha president,
    // Student Delta secretary — see MembershipSeeder). IT Guild: only a
    // President (Student Beta) — Secretary is open.
    $this->computingSociety = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->adviserTwo = User::where('email', 'adviser-two@nu-lipa.edu.ph')->firstOrFail();
    $this->studentBeta = User::where('email', 'student-beta@students.nu-lipa.edu.ph')->firstOrFail();

    Notification::fake();
});

function fileJoinRequest(Organization $org): OrganizationJoinRequest
{
    $student = User::factory()->create();

    return test()->requestAction->execute($student, $org);
}

test('index only shows requests for the acting adviser\'s own organization', function () {
    $forItGuild = fileJoinRequest($this->itGuild);
    fileJoinRequest($this->computingSociety);

    $response = $this->actingAs($this->adviserTwo)
        ->withoutVite()
        ->get(route('review.join-requests.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('review/join-requests/index')
        ->has('queue', 1)
        ->where('queue.0.id', $forItGuild->id)
        // Regression: OrganizationMembership::pluck('position') returns
        // cast OfficerPosition enum instances, not raw strings — comparing
        // them against ->value used to silently never exclude a filled
        // position from this list.
        ->where('queue.0.open_positions', ['secretary'])
    );
});

test('open_positions is empty once both officer slots are filled', function () {
    $forComputingSociety = fileJoinRequest($this->computingSociety);
    $adviserOne = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    $response = $this->actingAs($adviserOne)
        ->withoutVite()
        ->get(route('review.join-requests.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('queue.0.id', $forComputingSociety->id)
        ->where('queue.0.open_positions', [])
    );
});

test('an active officer sees their own organization\'s requests too', function () {
    $forItGuild = fileJoinRequest($this->itGuild);
    fileJoinRequest($this->computingSociety);

    $response = $this->actingAs($this->studentBeta) // President of IT Guild
        ->withoutVite()
        ->get(route('review.join-requests.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->has('queue', 1)
        ->where('queue.0.id', $forItGuild->id)
    );
});

test('a stranger to both organizations sees an empty queue', function () {
    fileJoinRequest($this->itGuild);
    fileJoinRequest($this->computingSociety);

    $stranger = User::factory()->create();

    $response = $this->actingAs($stranger)->withoutVite()->get(route('review.join-requests.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page->has('queue', 0));
});

test('the adviser can approve a request, binding the student and notifying them', function () {
    $joinRequest = fileJoinRequest($this->itGuild);

    $membership = $this->approveAction->execute($this->adviserTwo, $joinRequest, OfficerPosition::Secretary);

    expect($membership->is_active)->toBeTrue();
    expect($membership->position)->toBe(OfficerPosition::Secretary);
    expect($membership->user_id)->toBe($joinRequest->user_id);

    $joinRequest->refresh();
    expect($joinRequest->status)->toBe(JoinRequestStatus::Approved);
    expect($joinRequest->decided_by)->toBe($this->adviserTwo->id);
    expect($joinRequest->decided_at)->not->toBeNull();

    Notification::assertSentTo($joinRequest->user, JoinRequestApprovedNotification::class);
});

test('approving is blocked when the chosen position is already filled', function () {
    $joinRequest = fileJoinRequest($this->computingSociety);
    $adviserOne = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    expect(fn () => $this->approveAction->execute($adviserOne, $joinRequest, OfficerPosition::President))
        ->toThrow(ValidationException::class);

    $joinRequest->refresh();
    expect($joinRequest->status)->toBe(JoinRequestStatus::Pending);
});

test('approving is blocked for a student not yet SDAO-verified', function () {
    Notification::fake();
    $unverified = User::factory()->unverifiedAccount()->create();
    $joinRequest = $this->requestAction->execute($unverified, $this->itGuild);

    expect(fn () => $this->approveAction->execute($this->adviserTwo, $joinRequest, OfficerPosition::Secretary))
        ->toThrow(ValidationException::class);

    expect(OrganizationMembership::where('user_id', $unverified->id)->exists())->toBeFalse();
});

test('the adviser can decline a request; no membership is created and the student is notified', function () {
    $joinRequest = fileJoinRequest($this->itGuild);
    $studentId = $joinRequest->user_id;

    $this->declineAction->execute($this->adviserTwo, $joinRequest, 'Not the right fit right now.');

    $joinRequest->refresh();
    expect($joinRequest->status)->toBe(JoinRequestStatus::Declined);
    expect($joinRequest->decision_comment)->toBe('Not the right fit right now.');
    expect(OrganizationMembership::where('user_id', $studentId)->exists())->toBeFalse();

    Notification::assertSentTo($joinRequest->user, JoinRequestDeclinedNotification::class);
});

test('a request that was already decided cannot be decided again', function () {
    $joinRequest = fileJoinRequest($this->itGuild);
    $this->approveAction->execute($this->adviserTwo, $joinRequest, OfficerPosition::Secretary);

    expect(fn () => $this->declineAction->execute($this->adviserTwo, $joinRequest->fresh(), null))
        ->toThrow(ValidationException::class);
});

// ── HTTP: authorization boundary ──────────────────────────────────────────

test('HTTP: a non-adviser, non-officer gets 403 on approve and decline', function () {
    $joinRequest = fileJoinRequest($this->itGuild);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->post(route('review.join-requests.approve', $joinRequest), ['position' => 'secretary'])
        ->assertForbidden();

    $this->actingAs($stranger)
        ->post(route('review.join-requests.decline', $joinRequest))
        ->assertForbidden();
});

test('HTTP: the adviser can approve end-to-end and lands back on the queue', function () {
    $joinRequest = fileJoinRequest($this->itGuild);

    $response = $this->actingAs($this->adviserTwo)
        ->post(route('review.join-requests.approve', $joinRequest), ['position' => 'secretary']);

    $response->assertRedirect(route('review.join-requests.index'));
    expect($joinRequest->fresh()->status)->toBe(JoinRequestStatus::Approved);
});
