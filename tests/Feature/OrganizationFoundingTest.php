<?php

use App\Enums\DocumentStatus;
use App\Enums\OfficerPosition;
use App\Enums\OrganizationType;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use App\Registrations\SubmitOrganizationRegistration;
use App\Registrations\UpdateOrganizationRegistration;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->submitAction = app(SubmitOrganizationRegistration::class);
    $this->school = School::where('name', 'School of Computing and IT')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
});

function foundingOrgPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Founding Org',
        'programId' => null,
        'organizationType' => OrganizationType::CoCurricular,
        'purposeOfOrganization' => 'Description.',
        'contactPerson' => 'Contact Person',
        'contactNo' => '09170000000',
        'emailAddress' => 'contact@example.test',
        'dateOrganized' => '2020-06-01',
        'attachmentFiles' => registrationAttachmentFiles(),
    ], $overrides);
}

test('adviser-search reports an unbound adviser as available and a bound one as unavailable', function () {
    $available = User::factory()->create(['name' => 'Available Adviser']);
    RoleAssignment::create(['user_id' => $available->id, 'role' => Role::Adviser->value]);

    $bound = User::factory()->create(['name' => 'Bound Adviser']);
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    RoleAssignment::create(['user_id' => $bound->id, 'role' => Role::Adviser->value, 'organization_id' => $org->id]);

    $response = $this->actingAs($this->sdaoA)
        ->get(route('registrations.adviser-search', ['q' => 'Adviser']))
        ->assertOk();

    $results = collect($response->json('advisers'))->keyBy('id');

    expect($results[$available->id]['is_available'])->toBeTrue();
    expect($results[$bound->id]['is_available'])->toBeFalse();
});

test('a non-adviser account never appears in adviser-search results', function () {
    $notAnAdviser = User::factory()->create(['name' => 'Just A Student']);

    $response = $this->actingAs($this->sdaoA)
        ->get(route('registrations.adviser-search', ['q' => 'Just A Student']))
        ->assertOk();

    expect(collect($response->json('advisers'))->pluck('id'))->not->toContain($notAnAdviser->id);
});

test('approval-time race guard blocks approving a second proposal that picked the now-bound adviser', function () {
    $studentA = User::factory()->create();
    $studentB = User::factory()->create();
    $sharedAdviser = User::factory()->create();
    RoleAssignment::create(['user_id' => $sharedAdviser->id, 'role' => Role::Adviser->value]);

    $docA = $this->submitAction->execute(
        ...foundingOrgPayload(['name' => 'Org A', 'emailAddress' => 'a@example.test']),
        actor: $studentA,
        schoolId: $this->school->id,
        adviserId: $sharedAdviser->id,
    );
    $docB = $this->submitAction->execute(
        ...foundingOrgPayload(['name' => 'Org B', 'emailAddress' => 'b@example.test']),
        actor: $studentB,
        schoolId: $this->school->id,
        adviserId: $sharedAdviser->id,
    );

    // Org A gets fully approved first — binds the shared adviser to Org A.
    $this->actingAs($this->sdaoA)->post(route('review.registrations.approve', $docA));
    $docA->refresh();
    $this->actingAs($this->sdaoB)->post(route('review.registrations.approve', $docA));
    $docA->refresh();
    expect($docA->status)->toBe(DocumentStatus::Approved);
    expect(RoleAssignment::where('user_id', $sharedAdviser->id)->where('role', Role::Adviser->value)->first()->organization_id)
        ->toBe($docA->organization_id);

    // Approving Org B now must fail the race-condition re-check.
    $response = $this->actingAs($this->sdaoA)->post(route('review.registrations.approve', $docB));
    $response->assertSessionHasErrors('approve');

    $docB->refresh();
    expect($docB->status)->toBe(DocumentStatus::InReview);
    expect(OrganizationMembership::where('organization_id', $docB->organization_id)->exists())->toBeFalse();
});

test('return-for-revision preserves the ability to pick a new adviser and resubmit', function () {
    $student = User::factory()->create();
    $badAdviser = User::factory()->create();
    RoleAssignment::create(['user_id' => $badAdviser->id, 'role' => Role::Adviser->value]);
    $goodAdviser = User::factory()->create();
    RoleAssignment::create(['user_id' => $goodAdviser->id, 'role' => Role::Adviser->value]);

    $document = $this->submitAction->execute(
        ...foundingOrgPayload(),
        actor: $student,
        schoolId: $this->school->id,
        adviserId: $badAdviser->id,
    );

    $this->actingAs($this->sdaoA)
        ->post(route('review.registrations.return', $document), ['comment' => 'Please pick a different adviser.'])
        ->assertRedirect(route('review.registrations.show', $document));

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::Returned);

    app(UpdateOrganizationRegistration::class)->execute(
        actor: $student,
        document: $document,
        organizationType: OrganizationType::CoCurricular,
        purposeOfOrganization: 'Description.',
        contactPerson: 'Contact Person',
        contactNo: '09170000000',
        emailAddress: 'contact@example.test',
        dateOrganized: '2020-06-01',
        adviserId: $goodAdviser->id,
    );

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::InReview);
    expect($document->registrationDetail->adviser_id)->toBe($goodAdviser->id);
});

test('the chosen adviser is bound only after Approval, never before', function () {
    $student = User::factory()->create();
    $adviser = User::factory()->create();
    RoleAssignment::create(['user_id' => $adviser->id, 'role' => Role::Adviser->value]);

    $document = $this->submitAction->execute(
        ...foundingOrgPayload(),
        actor: $student,
        schoolId: $this->school->id,
        adviserId: $adviser->id,
    );

    $stillUnbound = fn () => RoleAssignment::where('user_id', $adviser->id)
        ->where('role', Role::Adviser->value)
        ->first()
        ->organization_id === null;

    expect($document->status)->toBe(DocumentStatus::InReview);
    expect($stillUnbound())->toBeTrue();

    // First of two SDAO approvals — quorum not yet reached, still unbound.
    $this->actingAs($this->sdaoA)->post(route('review.registrations.approve', $document));
    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::InReview);
    expect($stillUnbound())->toBeTrue();

    // Second approval reaches quorum — NOW bound.
    $this->actingAs($this->sdaoB)->post(route('review.registrations.approve', $document));
    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::Approved);
    expect(RoleAssignment::where('user_id', $adviser->id)->where('role', Role::Adviser->value)->first()->organization_id)
        ->toBe($document->organization_id);
});

test('adviser and founding student remain unbound through every non-terminal state, and both bind together the moment quorum is met', function () {
    $student = User::factory()->create();
    $adviser = User::factory()->create();
    RoleAssignment::create(['user_id' => $adviser->id, 'role' => Role::Adviser->value]);

    $document = $this->submitAction->execute(
        ...foundingOrgPayload(),
        actor: $student,
        schoolId: $this->school->id,
        adviserId: $adviser->id,
    );

    $adviserUnbound = fn () => RoleAssignment::where('user_id', $adviser->id)
        ->where('role', Role::Adviser->value)
        ->first()
        ->organization_id === null;

    $studentUnbound = fn () => OrganizationMembership::where('user_id', $student->id)
        ->where('organization_id', $document->organization_id)
        ->doesntExist();

    // 1. InReview — just submitted.
    expect($document->status)->toBe(DocumentStatus::InReview);
    expect($adviserUnbound())->toBeTrue();
    expect($studentUnbound())->toBeTrue();

    // 2. Returned — SDAO returns it (no prior approval needed to do so).
    $this->actingAs($this->sdaoA)
        ->post(route('review.registrations.return', $document), ['comment' => 'Please clarify the description.']);
    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::Returned);
    expect($adviserUnbound())->toBeTrue();
    expect($studentUnbound())->toBeTrue();

    // 3. InReview again — resubmitted, resumes at the returning step.
    app(UpdateOrganizationRegistration::class)->execute(
        actor: $student,
        document: $document,
        organizationType: OrganizationType::CoCurricular,
        purposeOfOrganization: 'Revised description.',
        contactPerson: 'Contact Person',
        contactNo: '09170000000',
        emailAddress: 'contact@example.test',
        dateOrganized: '2020-06-01',
    );
    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::InReview);
    expect($adviserUnbound())->toBeTrue();
    expect($studentUnbound())->toBeTrue();

    // 4. First SDAO approval — quorum not yet met, still InReview, still unbound.
    $this->actingAs($this->sdaoA)->post(route('review.registrations.approve', $document));
    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::InReview);
    expect($adviserUnbound())->toBeTrue();
    expect($studentUnbound())->toBeTrue();

    // 5. Second SDAO approval — quorum met — BOTH bind together, now.
    $this->actingAs($this->sdaoB)->post(route('review.registrations.approve', $document));
    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::Approved);
    expect(RoleAssignment::where('user_id', $adviser->id)->where('role', Role::Adviser->value)->first()->organization_id)
        ->toBe($document->organization_id);
    expect(OrganizationMembership::where('user_id', $student->id)
        ->where('organization_id', $document->organization_id)
        ->where('is_active', true)
        ->where('position', OfficerPosition::President->value)
        ->exists())->toBeTrue();
});

test('Gate::authorize propose blocks a student who already has an active org', function () {
    $studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail(); // already President, Computing Society
    $adviser = User::factory()->create();
    RoleAssignment::create(['user_id' => $adviser->id, 'role' => Role::Adviser->value]);

    expect(fn () => $this->submitAction->execute(
        ...foundingOrgPayload(),
        actor: $studentAlpha,
        schoolId: $this->school->id,
        adviserId: $adviser->id,
    ))->toThrow(ValidationException::class);
});
