<?php

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Enums\Role;
use App\Models\ApprovalNotification;
use App\Models\OrganizationMembership;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use App\Registrations\SubmitOrganizationRegistration;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->action = app(SubmitOrganizationRegistration::class);
    $this->school = School::where('name', 'School of Computing and IT')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
});

/** A fresh, admin-provisioned adviser account with no organization yet (available). */
function availableAdviser(): User
{
    $adviser = User::factory()->create();
    RoleAssignment::create(['user_id' => $adviser->id, 'role' => Role::Adviser->value]);

    return $adviser;
}

function foundingPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Founding Test Org',
        'programId' => null,
        'organizationType' => OrganizationType::CoCurricular,
        'purposeOfOrganization' => 'A brand-new student organization.',
        'contactPerson' => 'Founding Student',
        'contactNo' => '09171234567',
        'emailAddress' => 'foundingorg@sdao.test',
        'dateOrganized' => '2020-06-01',
        'attachmentFiles' => registrationAttachmentFiles(),
    ], $overrides);
}

test('a bare, not-yet-affiliated Verified student can propose a brand-new organization', function () {
    $student = User::factory()->create();
    $adviser = availableAdviser();

    $document = $this->action->execute(
        ...foundingPayload(),
        actor: $student,
        schoolId: $this->school->id,
        adviserId: $adviser->id,
    );

    expect($document->status)->toBe(DocumentStatus::InReview);
    expect($document->form_type)->toBe(FormType::OrganizationRegistration);
    expect($document->current_step_position)->toBe(1);
    expect($document->organization->name)->toBe('Founding Test Org');
    expect($document->organization->school_id)->toBe($this->school->id);
});

test('the organization exists in a pending state — no adviser or officer binding yet', function () {
    $student = User::factory()->create();
    $adviser = availableAdviser();

    $document = $this->action->execute(
        ...foundingPayload(),
        actor: $student,
        schoolId: $this->school->id,
        adviserId: $adviser->id,
    );

    expect(RoleAssignment::where('user_id', $adviser->id)->where('role', Role::Adviser->value)->first()->organization_id)
        ->toBeNull();
    expect(OrganizationMembership::where('organization_id', $document->organization_id)->exists())
        ->toBeFalse();
});

test('submission creates a registration detail row with the chosen (pending) adviser', function () {
    $student = User::factory()->create();
    $adviser = availableAdviser();

    $document = $this->action->execute(
        ...foundingPayload(),
        actor: $student,
        schoolId: $this->school->id,
        adviserId: $adviser->id,
    );

    expect($document->registrationDetail)->not->toBeNull();
    expect($document->registrationDetail->organization_type)->toBe(OrganizationType::CoCurricular);
    expect($document->registrationDetail->adviser_id)->toBe($adviser->id);
});

test('submission notifies both SDAO members', function () {
    $student = User::factory()->create();
    $adviser = availableAdviser();

    $document = $this->action->execute(
        ...foundingPayload(),
        actor: $student,
        schoolId: $this->school->id,
        adviserId: $adviser->id,
    );

    expect(ApprovalNotification::where('document_id', $document->id)->where('user_id', $this->sdaoA->id)->exists())->toBeTrue();
    expect(ApprovalNotification::where('document_id', $document->id)->where('user_id', $this->sdaoB->id)->exists())->toBeTrue();
});

test('an unverified account cannot propose an organization', function () {
    $student = User::factory()->unverifiedAccount()->create();
    $adviser = availableAdviser();

    expect(fn () => $this->action->execute(
        ...foundingPayload(),
        actor: $student,
        schoolId: $this->school->id,
        adviserId: $adviser->id,
    ))->toThrow(ValidationException::class);
});

test('the chosen adviser_id must actually hold Role::Adviser', function () {
    $student = User::factory()->create();
    $notAnAdviser = User::factory()->create();

    expect(fn () => $this->action->execute(
        ...foundingPayload(),
        actor: $student,
        schoolId: $this->school->id,
        adviserId: $notAnAdviser->id,
    ))->toThrow(ValidationException::class);
});
