<?php

use App\Enums\OfficerChangeRequestStatus;
use App\Enums\OfficerPosition;
use App\Enums\OfficerSeatEndReason;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\OfficerChangeApprovedNotification;
use App\Notifications\OfficerChangeDeclinedNotification;
use App\Notifications\OfficerSeatEndedNotification;
use App\Notifications\OfficerSeatGrantedNotification;
use App\Organizations\Admin\ApproveOfficerChange;
use App\Organizations\Admin\DeclineOfficerChange;
use App\Organizations\BindOrganizationOfficer;
use App\Organizations\RequestOfficerChange;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Support\Facades\Notification;

/**
 * Who is told what when a seat changes. Every path to a seat tells the person
 * who got it; every path that takes a seat away tells the person who lost it.
 * The nominee of a change request is told on approval only (not at filing, not
 * on a decline or withdrawal): until it is approved nothing has happened to
 * them, so a notice would only create an expectation that may never be met.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->sdao = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->president = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->secretary = User::where('email', 'student-delta@students.nu-lipa.edu.ph')->firstOrFail();
});

function notifiableStudent(): User
{
    return User::factory()->create(['account_status' => 'verified']);
}

// ── Adviser bind ────────────────────────────────────────────────────────────

test('binding a student tells them they got the seat, and tells the replaced officer their seat ended', function () {
    Notification::fake();
    $student = notifiableStudent();

    app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, $student, OfficerPosition::President);

    Notification::assertSentTo($student, OfficerSeatGrantedNotification::class, fn ($n) => $n->position === OfficerPosition::President && $n->organization->is($this->org));
    Notification::assertSentTo($this->president, OfficerSeatEndedNotification::class, fn ($n) => $n->reason === OfficerSeatEndReason::Replaced && $n->position === OfficerPosition::President);
    // The remaining officer is not bothered.
    Notification::assertNotSentTo($this->secretary, OfficerSeatEndedNotification::class);
    Notification::assertNotSentTo($student, OfficerSeatEndedNotification::class);
});

test('binding into a vacant seat only tells the new officer', function () {
    $this->secretary->organizationMemberships()->active()->update(['is_active' => false, 'ended_at' => now()]);
    Notification::fake();
    $student = notifiableStudent();

    app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, $student, OfficerPosition::Secretary);

    Notification::assertSentTo($student, OfficerSeatGrantedNotification::class);
    Notification::assertNotSentTo($this->secretary, OfficerSeatEndedNotification::class);
    Notification::assertCount(1);
});

test('moving a sitting officer to the other seat tells the replaced holder of THAT seat, not the mover', function () {
    Notification::fake();

    // Delta (Secretary) is promoted to President: Alpha (President) is replaced.
    app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, $this->secretary, OfficerPosition::President);

    Notification::assertSentTo($this->president, OfficerSeatEndedNotification::class);
    Notification::assertNotSentTo($this->secretary, OfficerSeatEndedNotification::class);
    Notification::assertSentTo($this->secretary, OfficerSeatGrantedNotification::class);
});

test('re-binding the sitting holder into their own seat does not tell them it ended', function () {
    Notification::fake();

    app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, $this->president, OfficerPosition::President);

    Notification::assertNotSentTo($this->president, OfficerSeatEndedNotification::class);
});

// ── Manage Officers: deactivate ─────────────────────────────────────────────

test('deactivating an officer tells them, once', function () {
    Notification::fake();
    $membership = OrganizationMembership::where('user_id', $this->secretary->id)->active()->firstOrFail();
    $url = route('officers.destroy', ['organization' => $this->org->id, 'membership' => $membership->id]);

    $this->actingAs($this->adviser)->delete($url)->assertRedirect();

    Notification::assertSentTo($this->secretary, OfficerSeatEndedNotification::class, fn ($n) => $n->reason === OfficerSeatEndReason::Deactivated && $n->position === OfficerPosition::Secretary);

    // Deactivating the already-closed row again must not send a second notice.
    $this->actingAs($this->adviser)->delete($url)->assertRedirect();
    Notification::assertSentToTimes($this->secretary, OfficerSeatEndedNotification::class, 1);
});

// ── Officer change request ──────────────────────────────────────────────────

test('an approved change tells the nominee, the requester and the replaced officer — each once', function () {
    $nominee = notifiableStudent();
    $request = app(RequestOfficerChange::class)->execute($this->president, OfficerPosition::Secretary, $nominee);
    Notification::fake();

    app(ApproveOfficerChange::class)->execute($this->sdao, $request);

    Notification::assertSentToTimes($nominee, OfficerChangeApprovedNotification::class, 1);
    Notification::assertSentToTimes($this->president, OfficerChangeApprovedNotification::class, 1);
    Notification::assertSentToTimes($this->secretary, OfficerSeatEndedNotification::class, 1);
    Notification::assertNotSentTo($this->secretary, OfficerChangeApprovedNotification::class);
    Notification::assertNotSentTo($nominee, OfficerSeatEndedNotification::class);
});

test('a requester who asked to replace themselves gets the seat-ended notice only, not a second "approved" one', function () {
    $nominee = notifiableStudent();
    $request = app(RequestOfficerChange::class)->execute($this->president, OfficerPosition::President, $nominee);
    Notification::fake();

    app(ApproveOfficerChange::class)->execute($this->sdao, $request);

    Notification::assertSentToTimes($this->president, OfficerSeatEndedNotification::class, 1);
    Notification::assertNotSentTo($this->president, OfficerChangeApprovedNotification::class);
    Notification::assertSentToTimes($nominee, OfficerChangeApprovedNotification::class, 1);
});

test('the nominee is told nothing at filing, nothing on a decline and nothing on a withdrawal', function () {
    Notification::fake();
    $nominee = notifiableStudent();

    $request = app(RequestOfficerChange::class)->execute($this->president, OfficerPosition::Secretary, $nominee);
    Notification::assertNotSentTo($nominee, OfficerChangeApprovedNotification::class);
    Notification::assertNothingSentTo($nominee);

    app(DeclineOfficerChange::class)->execute($this->sdao, $request, 'No.');
    Notification::assertNothingSentTo($nominee);
    Notification::assertSentTo($this->president, OfficerChangeDeclinedNotification::class);

    $second = app(RequestOfficerChange::class)->execute($this->president, OfficerPosition::Secretary, $nominee);
    app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, notifiableStudent(), OfficerPosition::President);
    expect($second->fresh()->status)->toBe(OfficerChangeRequestStatus::Withdrawn);
    Notification::assertNothingSentTo($nominee);
});

// ── Content and channels ────────────────────────────────────────────────────

test('both new notices use the app\'s mail + database pattern and say only what the recipient needs', function () {
    $granted = new OfficerSeatGrantedNotification($this->org, OfficerPosition::President);
    $ended = new OfficerSeatEndedNotification($this->org, OfficerPosition::President, OfficerSeatEndReason::Replaced);

    expect($granted->via($this->president))->toBe(['mail', 'database']);
    expect($ended->via($this->president))->toBe(['mail', 'database']);
    expect($granted->viaConnections())->toBe(['database' => 'sync', 'mail' => 'database']);

    $grantedMail = $granted->toMail($this->president)->render();
    expect($grantedMail)->toContain('President')->toContain('Computing Society');

    $endedMail = $ended->toMail($this->president)->render();
    expect($endedMail)->toContain('President')->toContain('Computing Society')->toContain('no longer hold');
    // Nothing about who replaced them or any request.
    expect($endedMail)->not->toContain('nominee')->not->toContain('request');

    expect($ended->toArray($this->president))->toMatchArray(['kind' => 'officer_seat_ended', 'status' => 'ended']);
    expect($granted->toArray($this->president))->toMatchArray(['kind' => 'officer_seat_granted', 'status' => 'approved']);
});

test('real delivery writes the in-app notification rows', function () {
    $student = notifiableStudent();

    app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, $student, OfficerPosition::President);

    expect($student->notifications()->get()->pluck('data.kind')->all())->toContain('officer_seat_granted');
    expect($this->president->notifications()->get()->pluck('data.kind')->all())->toContain('officer_seat_ended');
});

test('a notification failure never undoes or blocks the seat change', function () {
    app()->instance(Dispatcher::class, new class implements Dispatcher
    {
        public function send($notifiables, $notification): void
        {
            throw new RuntimeException('mail provider down');
        }

        public function sendNow($notifiables, $notification, ?array $channels = null): void
        {
            throw new RuntimeException('mail provider down');
        }
    });
    $student = notifiableStudent();

    $membership = app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, $student, OfficerPosition::President);

    expect($membership->is_active)->toBeTrue();
    expect(OrganizationMembership::where('organization_id', $this->org->id)->where('position', 'president')->active()->pluck('user_id')->all())->toBe([$student->id]);
});
