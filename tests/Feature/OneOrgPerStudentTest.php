<?php

use App\Approval\ApprovalEngine;
use App\Approval\Exceptions\InvalidTransitionException;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OfficerPosition;
use App\Enums\OrganizationType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationMembership;
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

    // Already bound (via MembershipSeeder) as active President of Computing Society.
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
});

function oneOrgPayload(): array
{
    return [
        'organizationType' => OrganizationType::CoCurricular,
        'description' => 'Description.',
        'contactPerson' => 'Contact Person',
        'contactNumber' => '09170000000',
        'contactEmail' => 'contact@example.test',
        'dateOrganized' => '2020-06-01',
        'roster' => ['Member One'],
    ];
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

test('a student with an in-flight registration for a different org is blocked from starting another', function () {
    $student = User::factory()->create();

    // Bind to orgA and submit — lands InReview.
    $membershipA = $this->bindAction->execute(
        actor: $this->adviserA,
        organization: $this->orgA,
        student: $student,
        position: OfficerPosition::Secretary,
    );
    $p = oneOrgPayload();
    $docA = $this->submitAction->execute(...$p, actor: $student, organization: $this->orgA);
    expect($docA->status)->toBe(DocumentStatus::InReview);

    // Independently deactivate the orgA membership (e.g. adviser removes them)
    // WITHOUT the registration document itself being resolved — the loophole
    // this guard exists to close.
    $membershipA->update(['is_active' => false]);

    // Now bindable elsewhere (no active membership anywhere).
    $this->bindAction->execute(
        actor: $this->adviserB,
        organization: $this->orgB,
        student: $student,
        position: OfficerPosition::Secretary,
    );

    // But submitting orgB's registration is blocked — orgA's document is
    // still Draft/InReview/Returned.
    expect(fn () => $this->submitAction->execute(...oneOrgPayload(), actor: $student, organization: $this->orgB))
        ->toThrow(ValidationException::class);

    expect(Document::where('form_type', FormType::OrganizationRegistration->value)
        ->where('organization_id', $this->orgB->id)
        ->exists())->toBeFalse();
});

test('a rejected registration frees the student to start a new one elsewhere', function () {
    $student = User::factory()->create();

    $this->bindAction->execute(
        actor: $this->adviserA,
        organization: $this->orgA,
        student: $student,
        position: OfficerPosition::Secretary,
    );
    $docA = $this->submitAction->execute(...oneOrgPayload(), actor: $student, organization: $this->orgA);
    expect($docA->status)->toBe(DocumentStatus::InReview);

    // Reject via the real HTTP review action — the deactivation side effect
    // lives in the controller, not the engine.
    $this->actingAs($this->sdaoA)
        ->post(route('review.registrations.reject', $docA), ['comment' => 'Incomplete paperwork.'])
        ->assertRedirect(route('review.registrations.index'));

    $docA->refresh();
    expect($docA->status)->toBe(DocumentStatus::Rejected);
    expect(OrganizationMembership::where('user_id', $student->id)
        ->where('organization_id', $this->orgA->id)
        ->where('is_active', true)
        ->exists())->toBeFalse();

    // Now free to be bound to, and submit for, a different org.
    $this->bindAction->execute(
        actor: $this->adviserB,
        organization: $this->orgB,
        student: $student,
        position: OfficerPosition::Secretary,
    );
    $docB = $this->submitAction->execute(...oneOrgPayload(), actor: $student, organization: $this->orgB);

    expect($docB->status)->toBe(DocumentStatus::InReview);
});

test('a student is locked in once Approved — irreversibly, unlike Rejected', function () {
    $student = User::factory()->create();

    $this->bindAction->execute(
        actor: $this->adviserA,
        organization: $this->orgA,
        student: $student,
        position: OfficerPosition::Secretary,
    );
    $docA = $this->submitAction->execute(...oneOrgPayload(), actor: $student, organization: $this->orgA);

    $this->engine->approve($docA, $this->sdaoA);
    $docA->refresh();
    $this->engine->approve($docA, $this->sdaoB);
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

    // Membership remains active throughout.
    expect(OrganizationMembership::where('user_id', $student->id)
        ->where('organization_id', $this->orgA->id)
        ->where('is_active', true)
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
