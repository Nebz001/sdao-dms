<?php

use App\Approval\ApprovalEngine;
use App\Approval\Exceptions\InvalidTransitionException;
use App\Enums\DocumentStatus;
use App\Enums\OfficerPosition;
use App\Enums\OrganizationType;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use App\Organizations\BindOrganizationOfficer;
use App\Registrations\SubmitOrganizationRegistration;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->bindAction = app(BindOrganizationOfficer::class);
    $this->submitAction = app(SubmitOrganizationRegistration::class);
    $this->engine = app(ApprovalEngine::class);

    $this->orgA = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->orgB = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->adviserA = User::where('email', 'adviser-one@sdao.test')->firstOrFail(); // Computing Society
    $this->adviserB = User::where('email', 'adviser-two@sdao.test')->firstOrFail(); // IT Guild
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $this->school = School::where('name', 'School of Computing and IT')->firstOrFail();

    // Already bound (via MembershipSeeder) as active President of Computing Society.
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
});

/** A fresh, admin-provisioned adviser account with no organization yet (available). */
function unboundAdviser(): User
{
    $adviser = User::factory()->create();
    RoleAssignment::create(['user_id' => $adviser->id, 'role' => Role::Adviser->value]);

    return $adviser;
}

function oneOrgPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'One-Org Test Org',
        'programId' => null,
        'organizationType' => OrganizationType::CoCurricular,
        'purposeOfOrganization' => 'Description.',
        'contactPerson' => 'Contact Person',
        'contactNo' => '09170000000',
        'emailAddress' => 'contact@example.test',
        'dateOrganized' => '2020-06-01',
        'roster' => ['Member One'],
    ], $overrides);
}

test('a student with an active officer role elsewhere is blocked from a new binding', function () {
    expect(fn () => $this->bindAction->execute(
        actor: $this->adviserB,
        organization: $this->orgB,
        student: $this->studentAlpha, // already active President of orgA
        position: OfficerPosition::Secretary,
    ))->toThrow(ValidationException::class);

    expect(OrganizationMembership::where('user_id', $this->studentAlpha->id)
        ->where('organization_id', $this->orgB->id)
        ->exists())->toBeFalse();
});

test('a student with an in-flight proposal is blocked from founding a second organization', function () {
    $student = User::factory()->create();
    $adviser1 = unboundAdviser();
    $adviser2 = unboundAdviser();

    $this->submitAction->execute(
        ...oneOrgPayload(),
        actor: $student,
        schoolId: $this->school->id,
        adviserId: $adviser1->id,
    );

    // A founding proposal has NO membership yet (Phase 2 item 5) — the
    // in-flight DOCUMENT is the only thing blocking a second proposal.
    expect(fn () => $this->submitAction->execute(
        ...oneOrgPayload(['name' => 'Second Org', 'emailAddress' => 'second@example.test']),
        actor: $student,
        schoolId: $this->school->id,
        adviserId: $adviser2->id,
    ))->toThrow(ValidationException::class);

    expect(Organization::where('name', 'Second Org')->exists())->toBeFalse();
});

test('a rejected proposal frees the student to found a different organization', function () {
    $student = User::factory()->create();
    $adviser1 = unboundAdviser();
    $adviser2 = unboundAdviser();

    $docA = $this->submitAction->execute(
        ...oneOrgPayload(),
        actor: $student,
        schoolId: $this->school->id,
        adviserId: $adviser1->id,
    );

    // Reject via the real HTTP review action.
    $this->actingAs($this->sdaoA)
        ->post(route('review.registrations.reject', $docA), ['comment' => 'Incomplete paperwork.'])
        ->assertRedirect(route('review.registrations.index'));

    $docA->refresh();
    expect($docA->status)->toBe(DocumentStatus::Rejected);

    // Nothing left over from the rejected attempt: a founding registration
    // never has a membership before Approval, so there is none to clean up,
    // and the chosen adviser was never bound either.
    expect(OrganizationMembership::where('user_id', $student->id)->exists())->toBeFalse();
    expect(RoleAssignment::where('user_id', $adviser1->id)->where('role', Role::Adviser->value)->value('organization_id'))
        ->toBeNull();

    // And no in-flight document status is left blocking them — the guard
    // itself proves this: a second submission for a DIFFERENT org succeeds.
    $docB = $this->submitAction->execute(
        ...oneOrgPayload(['name' => 'Second Org', 'emailAddress' => 'second@example.test']),
        actor: $student,
        schoolId: $this->school->id,
        adviserId: $adviser2->id,
    );

    expect($docB->status)->toBe(DocumentStatus::InReview);
});

test('a student is locked in once Approved — irreversibly, unlike Rejected', function () {
    $student = User::factory()->create();
    $adviser1 = unboundAdviser();

    $docA = $this->submitAction->execute(
        ...oneOrgPayload(),
        actor: $student,
        schoolId: $this->school->id,
        adviserId: $adviser1->id,
    );

    // Approve via the real HTTP review action — binding (Phase 2 item 5)
    // happens inside ApproveOrganizationRegistration, not the raw engine.
    $this->actingAs($this->sdaoA)->post(route('review.registrations.approve', $docA));
    $docA->refresh();
    $this->actingAs($this->sdaoB)->post(route('review.registrations.approve', $docA));
    $docA->refresh();
    expect($docA->status)->toBe(DocumentStatus::Approved);

    // Still blocked from being bound elsewhere — same guard as the InReview case.
    expect(fn () => $this->bindAction->execute(
        actor: $this->adviserB,
        organization: $this->orgB,
        student: $student,
        position: OfficerPosition::Secretary,
    ))->toThrow(ValidationException::class);

    // Unlike Rejected, there is no reject path left to free them — Approved is terminal.
    expect(fn () => $this->engine->reject($docA, $this->sdaoA, 'Too late.'))
        ->toThrow(InvalidTransitionException::class);

    // Founding student was bound as President upon Approval and remains active.
    expect(OrganizationMembership::where('user_id', $student->id)
        ->where('organization_id', $docA->organization_id)
        ->where('is_active', true)
        ->where('position', OfficerPosition::President->value)
        ->exists())->toBeTrue();
});

test('an adviser can still rebind within the SAME org (turnover) without tripping the guard', function () {
    $newSecretary = User::factory()->create();

    // studentAlpha is already active President of orgA; binding a different
    // student as Secretary of the SAME org must not be blocked.
    $membership = $this->bindAction->execute(
        actor: $this->adviserA,
        organization: $this->orgA,
        student: $newSecretary,
        position: OfficerPosition::Secretary,
    );

    expect($membership->is_active)->toBeTrue();
    expect(OrganizationMembership::where('organization_id', $this->orgA->id)
        ->where('is_active', true)
        ->count())->toBeGreaterThanOrEqual(2); // studentAlpha (President) + newSecretary
});
