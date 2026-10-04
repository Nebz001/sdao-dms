<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\JoinRequestStatus;
use App\Enums\OfficerChangeRequestStatus;
use App\Enums\OfficerPosition;
use App\Enums\OrganizationType;
use App\Models\Document;
use App\Models\OfficerChangeRequest;
use App\Models\Organization;
use App\Models\OrganizationJoinRequest;
use App\Models\OrganizationMembership;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use App\Organizations\Admin\ApproveOfficerChange;
use App\Organizations\Admin\DeclineOfficerChange;
use App\Organizations\ApproveJoinRequest;
use App\Organizations\BindOrganizationOfficer;
use App\Organizations\RequestOfficerChange;
use App\Registrations\ApproveOrganizationRegistration;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Two actors changing the same seat at the same time. A real race can't be
 * reproduced on the in-memory SQLite test database (single connection), so
 * these tests simulate the two outcomes that matter:
 *
 *  1. STALE READ — an actor holds a copy loaded BEFORE the other actor
 *     finished (the page was rendered, the admin clicked later). The action
 *     must re-check against fresh state under its lock, not trust the copy.
 *  2. LOST RACE — the other actor lands a competing row in the window between
 *     this action's checks and its insert (simulated with a `creating` hook).
 *     The database's unique index rejects the loser; the user must get a clear
 *     validation message, never a 500, and nothing may be half-applied.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    Notification::fake();
    $this->withoutVite();
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->president = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->secretary = User::where('email', 'student-delta@students.nu-lipa.edu.ph')->firstOrFail();
});

function verifiedStudent(): User
{
    return User::factory()->create(['account_status' => 'verified']);
}

function activeHolders(Organization $org, OfficerPosition $position): array
{
    return OrganizationMembership::where('organization_id', $org->id)
        ->where('position', $position->value)->active()->pluck('user_id')->all();
}

/**
 * Simulates the rival: the instant OUR membership row is about to be inserted,
 * a competing active row for the same seat lands first.
 */
function rivalClaimsSeatBeforeInsert(Organization $org, OfficerPosition $position): void
{
    OrganizationMembership::creating(function (OrganizationMembership $m) use ($org, $position) {
        static $fired = false;

        if ($fired || $m->organization_id !== $org->id || $m->position !== $position) {
            return;
        }

        $fired = true;
        DB::table('organization_memberships')->insert([
            'user_id' => verifiedStudent()->id,
            'organization_id' => $org->id,
            'position' => $position->value,
            'academic_year' => '2026-2027',
            'is_active' => true,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });
}

// ── Adviser bind ────────────────────────────────────────────────────────────

test('a bind that loses the race gets a clear message, and the seat is left untouched', function () {
    rivalClaimsSeatBeforeInsert($this->org, OfficerPosition::President);
    $nominee = verifiedStudent();

    expect(fn () => app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, $nominee, OfficerPosition::President))
        ->toThrow(ValidationException::class, 'Someone else just changed this seat');

    // Rolled back as a unit: the sitting president was NOT closed, and the
    // nominee holds nothing.
    expect(activeHolders($this->org, OfficerPosition::President))->toBe([$this->president->id]);
    expect(OrganizationMembership::where('user_id', $nominee->id)->exists())->toBeFalse();
});

test('over HTTP a lost bind race is a validation error on the form, not a 500', function () {
    rivalClaimsSeatBeforeInsert($this->org, OfficerPosition::President);
    $nominee = verifiedStudent();

    $this->actingAs($this->adviser)
        ->from(route('officers.index', $this->org))
        ->post(route('officers.store', $this->org), ['user_id' => $nominee->id, 'position' => 'president'])
        ->assertRedirect(route('officers.index', $this->org))
        ->assertSessionHasErrors('user_id');
});

// ── SDAO finalize ───────────────────────────────────────────────────────────

test('an approval that loses the race stays Pending and changes nothing', function () {
    $nominee = verifiedStudent();
    $request = app(RequestOfficerChange::class)->execute($this->president, OfficerPosition::Secretary, $nominee);
    rivalClaimsSeatBeforeInsert($this->org, OfficerPosition::Secretary);

    expect(fn () => app(ApproveOfficerChange::class)->execute($this->sdaoA, $request))
        ->toThrow(ValidationException::class, 'Someone else just changed this seat');

    expect($request->fresh()->status)->toBe(OfficerChangeRequestStatus::Pending);
    expect(activeHolders($this->org, OfficerPosition::Secretary))->toBe([$this->secretary->id]);
});

test('two admins approving the same request: the second, working from a stale copy, is told it was already decided', function () {
    $nominee = verifiedStudent();
    $request = app(RequestOfficerChange::class)->execute($this->president, OfficerPosition::Secretary, $nominee);
    $staleCopy = OfficerChangeRequest::findOrFail($request->id);

    app(ApproveOfficerChange::class)->execute($this->sdaoA, $request);

    expect(fn () => app(ApproveOfficerChange::class)->execute($this->sdaoA, $staleCopy))
        ->toThrow(ValidationException::class, 'already been decided');

    // The nominee was bound exactly once — no second approval churned the seat.
    expect(OrganizationMembership::where('user_id', $nominee->id)->count())->toBe(1);
    expect(activeHolders($this->org, OfficerPosition::Secretary))->toBe([$nominee->id]);
});

test('a decline racing an approve cannot overwrite the decision that landed first', function () {
    $nominee = verifiedStudent();
    $request = app(RequestOfficerChange::class)->execute($this->president, OfficerPosition::Secretary, $nominee);
    $staleCopy = OfficerChangeRequest::findOrFail($request->id);

    app(ApproveOfficerChange::class)->execute($this->sdaoA, $request);

    expect(fn () => app(DeclineOfficerChange::class)->execute($this->sdaoA, $staleCopy, 'too late'))
        ->toThrow(ValidationException::class, 'already been decided');

    expect($request->fresh()->status)->toBe(OfficerChangeRequestStatus::Approved);
    expect($request->fresh()->decision_comment)->toBeNull();
});

// ── Filing a request ────────────────────────────────────────────────────────

test('the database itself refuses a second pending request for the same seat', function () {
    OfficerChangeRequest::factory()->create([
        'organization_id' => $this->org->id,
        'requested_by' => $this->president->id,
        'position' => OfficerPosition::Secretary,
        'status' => OfficerChangeRequestStatus::Pending,
    ]);

    // In its own savepoint: Postgres aborts the whole surrounding (test)
    // transaction after a constraint violation, so without it the inserts
    // below would fail with "current transaction is aborted".
    expect(fn () => DB::transaction(fn () => OfficerChangeRequest::factory()->create([
        'organization_id' => $this->org->id,
        'requested_by' => $this->secretary->id,
        'position' => OfficerPosition::Secretary,
        'status' => OfficerChangeRequestStatus::Pending,
    ])))->toThrow(UniqueConstraintViolationException::class);

    // A decided one never blocks, and the OTHER seat is independent.
    OfficerChangeRequest::factory()->create([
        'organization_id' => $this->org->id,
        'requested_by' => $this->secretary->id,
        'position' => OfficerPosition::Secretary,
        'status' => OfficerChangeRequestStatus::Declined,
    ]);
    OfficerChangeRequest::factory()->create([
        'organization_id' => $this->org->id,
        'requested_by' => $this->secretary->id,
        'position' => OfficerPosition::President,
        'status' => OfficerChangeRequestStatus::Pending,
    ]);
    expect(OfficerChangeRequest::pending()->where('organization_id', $this->org->id)->count())->toBe(2);
});

test('two officers filing for the same seat at once: the loser gets a clear message, not a 500', function () {
    OfficerChangeRequest::creating(function (OfficerChangeRequest $r) {
        static $fired = false;

        if ($fired) {
            return;
        }

        $fired = true;
        DB::table('officer_change_requests')->insert([
            'organization_id' => $r->organization_id,
            'requested_by' => $this->secretary->id,
            'position' => $r->position->value,
            'nominee_id' => verifiedStudent()->id,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    expect(fn () => app(RequestOfficerChange::class)->execute($this->president, OfficerPosition::Secretary, verifiedStudent()))
        ->toThrow(ValidationException::class, 'Someone else just changed this seat');
});

// ── Join requests ───────────────────────────────────────────────────────────

test('approving the same join request twice from a stale copy is refused the second time', function () {
    $student = verifiedStudent();
    $this->secretary->organizationMemberships()->active()->update(['is_active' => false, 'ended_at' => now()]);
    $request = OrganizationJoinRequest::factory()->create([
        'user_id' => $student->id,
        'organization_id' => $this->org->id,
        'status' => JoinRequestStatus::Pending,
    ]);
    $staleCopy = OrganizationJoinRequest::findOrFail($request->id);

    app(ApproveJoinRequest::class)->execute($this->adviser, $request, OfficerPosition::Secretary);

    expect(fn () => app(ApproveJoinRequest::class)->execute($this->adviser, $staleCopy, OfficerPosition::Secretary))
        ->toThrow(ValidationException::class, 'already been decided');
    expect(OrganizationMembership::where('user_id', $student->id)->count())->toBe(1);
});

test('a join approval that loses the seat race gets a clear message', function () {
    $student = verifiedStudent();
    $this->secretary->organizationMemberships()->active()->update(['is_active' => false, 'ended_at' => now()]);
    $request = OrganizationJoinRequest::factory()->create([
        'user_id' => $student->id,
        'organization_id' => $this->org->id,
        'status' => JoinRequestStatus::Pending,
    ]);
    rivalClaimsSeatBeforeInsert($this->org, OfficerPosition::Secretary);

    expect(fn () => app(ApproveJoinRequest::class)->execute($this->adviser, $request, OfficerPosition::Secretary))
        ->toThrow(ValidationException::class, 'Someone else just changed this seat');
    expect($request->fresh()->status)->toBe(JoinRequestStatus::Pending);
});

// ── Founding president ──────────────────────────────────────────────────────

test('a registration cannot be approved once its founding student is an active officer elsewhere', function () {
    // Alpha is already President of Computing Society; a second registration
    // filed by them (only possible via a double-submit race) must not make
    // them President of a SECOND org. The approval is refused and rolled back.
    $second = Organization::create(['name' => 'Second Founding Org', 'school_id' => $this->org->school_id, 'program_id' => $this->org->program_id]);
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $second->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $this->president->id,
    ]);
    OrganizationRegistrationDetail::factory()->create(['document_id' => $doc->id, 'organization_type' => OrganizationType::CoCurricular]);
    app(ApprovalEngine::class)->submit($doc, $this->president);
    $doc->refresh();
    app(ApprovalEngine::class)->approve($doc, $this->sdaoA);

    expect(fn () => app(ApproveOrganizationRegistration::class)->execute($doc->refresh(), User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail()))
        ->toThrow(ValidationException::class, 'founding student is now an active officer');

    expect($doc->fresh()->status)->toBe(DocumentStatus::InReview);
    expect(OrganizationMembership::where('user_id', $this->president->id)->active()->count())->toBe(1);
});
