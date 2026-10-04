<?php

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OfficerPosition;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Organizations\Admin\ApproveOfficerChange;
use App\Organizations\BindOrganizationOfficer;
use App\Organizations\EligibleOfficerCandidates;
use App\Organizations\RequestOfficerChange;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Eligibility to be (re-)bound is the one-organization-per-student rule and
 * nothing else: a CLOSED membership row — in this org or any other — is
 * history, never a bar. One rule, EligibleOfficerCandidates, shared by both
 * pickers and re-checked server-side by the bind, request and finalize paths.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    Notification::fake();
    $this->withoutVite();
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->president = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->secretary = User::where('email', 'student-delta@students.nu-lipa.edu.ph')->firstOrFail();
    $this->itPresident = User::where('email', 'student-beta@students.nu-lipa.edu.ph')->firstOrFail();
    $this->candidates = app(EligibleOfficerCandidates::class);
});

function formerOfficerOf(Organization $org, User $user, OfficerPosition $position = OfficerPosition::Secretary): User
{
    $user->update(['account_status' => 'verified']);
    OrganizationMembership::create([
        'user_id' => $user->id,
        'organization_id' => $org->id,
        'position' => $position->value,
        'academic_year' => '2025-2026',
        'is_active' => false,
        'started_at' => now()->subYear(),
        'ended_at' => now()->subMonths(2),
    ]);

    return $user->fresh();
}

/**
 * @return list<string>
 */
function pickerEmails(mixed $test, User $adviser, Organization $org, string $search): array
{
    $response = $test->actingAs($adviser)->get(route('officers.index', $org).'?search='.$search);
    $response->assertOk();

    return collect($response->viewData('page')['props']['students'])->pluck('email')->all();
}

test('a former officer of THIS org is eligible again — picker, bind, request and finalize all agree', function () {
    $former = formerOfficerOf($this->org, User::factory()->create(['email' => 'was-here@example.test']));

    expect($this->candidates->matches($this->org, $former))->toBeTrue();
    expect(pickerEmails($this, $this->adviser, $this->org, 'was-here@example.test'))->toBe(['was-here@example.test']);

    // Nominee typeahead for an officer of the same org.
    $this->actingAs($this->president)->getJson(route('organizations.officer-change.search', ['q' => 'was-here@example.test']))
        ->assertOk()->assertJsonPath('students.0.email', 'was-here@example.test');

    // Server-side: the adviser can bind them straight back in...
    $membership = app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, $former, OfficerPosition::Secretary);
    expect($membership->is_active)->toBeTrue();
    // ...and the old, closed row is untouched history alongside the new one.
    expect(OrganizationMembership::where('user_id', $former->id)->where('organization_id', $this->org->id)->count())->toBe(2);
    expect(OrganizationMembership::where('user_id', $former->id)->whereNotNull('ended_at')->count())->toBe(1);
});

test('a former officer can be nominated and approved through the officer-change path', function () {
    $former = formerOfficerOf($this->org, User::factory()->create());

    $request = app(RequestOfficerChange::class)->execute($this->president, OfficerPosition::Secretary, $former, 'Welcome back.');
    app(ApproveOfficerChange::class)->execute($this->sdaoA, $request);

    expect(OrganizationMembership::where('user_id', $former->id)->active()->where('organization_id', $this->org->id)
        ->where('position', 'secretary')->exists())->toBeTrue();
    expect($this->secretary->fresh()->organizationMemberships()->active()->exists())->toBeFalse();
});

test('a former officer of a DIFFERENT org is eligible here once their seat there is closed', function () {
    $former = formerOfficerOf($this->itGuild, User::factory()->create(['email' => 'was-elsewhere@example.test']));

    expect($this->candidates->matches($this->org, $former))->toBeTrue();
    expect(pickerEmails($this, $this->adviser, $this->org, 'was-elsewhere@example.test'))->toBe(['was-elsewhere@example.test']);

    $membership = app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, $former, OfficerPosition::Secretary);
    expect($membership->organization_id)->toBe($this->org->id);
});

test('an ACTIVE officer of a different org stays ineligible everywhere — the one-org rule is unchanged', function () {
    expect($this->candidates->matches($this->org, $this->itPresident))->toBeFalse();
    expect(pickerEmails($this, $this->adviser, $this->org, 'student-beta@students.nu-lipa.edu.ph'))->toBe([]);

    expect(fn () => app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, $this->itPresident, OfficerPosition::Secretary))
        ->toThrow(ValidationException::class);
});

test('a former officer who is now ACTIVE in another org is ineligible', function () {
    $former = formerOfficerOf($this->org, User::factory()->create());
    OrganizationMembership::create([
        'user_id' => $former->id,
        'organization_id' => $this->itGuild->id,
        'position' => 'secretary',
        'academic_year' => '2026-2027',
        'is_active' => true,
        'started_at' => now(),
    ]);

    expect($this->candidates->matches($this->org, $former))->toBeFalse();
});

test('a sitting officer of this org remains eligible (seat swap)', function () {
    expect($this->candidates->matches($this->org, $this->secretary))->toBeTrue();
});

test('the bind action re-checks eligibility server-side, not just in the picker', function () {
    $approver = User::factory()->create(['account_status' => 'verified']);
    RoleAssignment::create(['user_id' => $approver->id, 'role' => Role::Dean, 'school_id' => $this->org->school_id]);

    expect(fn () => app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, $approver, OfficerPosition::Secretary))
        ->toThrow(ValidationException::class);

    $inFlight = User::factory()->create(['account_status' => 'verified']);
    Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->itGuild->id,
        'status' => DocumentStatus::InReview,
        'submitted_by' => $inFlight->id,
    ]);

    expect(fn () => app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, $inFlight, OfficerPosition::Secretary))
        ->toThrow(ValidationException::class);
});
