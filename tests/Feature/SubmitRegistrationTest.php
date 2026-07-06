<?php

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Models\ApprovalNotification;
use App\Models\Organization;
use App\Models\User;
use App\Registrations\SubmitOrganizationRegistration;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->action = app(SubmitOrganizationRegistration::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $this->studentDelta = User::where('email', 'student-delta@sdao.test')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
});

function registrationPayload(): array
{
    return [
        'organizationType' => OrganizationType::CoCurricular,
        'description' => 'A computing society for students.',
        'contactPerson' => 'Student Alpha',
        'contactNumber' => '09171234567',
        'contactEmail' => 'cs@sdao.test',
        'dateOrganized' => '2020-06-01',
        'roster' => ['Student Alpha', 'Student Delta'],
    ];
}

test('affiliated president can submit a registration', function () {
    $p = registrationPayload();
    $document = app(SubmitOrganizationRegistration::class)->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        organizationType: $p['organizationType'],
        description: $p['description'],
        contactPerson: $p['contactPerson'],
        contactNumber: $p['contactNumber'],
        contactEmail: $p['contactEmail'],
        dateOrganized: $p['dateOrganized'],
        roster: $p['roster'],
    );

    expect($document->status)->toBe(DocumentStatus::InReview);
    expect($document->form_type)->toBe(FormType::OrganizationRegistration);
    expect($document->current_step_position)->toBe(1);
    expect($document->organization_id)->toBe($this->org->id);
});

test('submission creates a registration detail row', function () {
    $p = registrationPayload();
    $document = app(SubmitOrganizationRegistration::class)->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        organizationType: $p['organizationType'],
        description: $p['description'],
        contactPerson: $p['contactPerson'],
        contactNumber: $p['contactNumber'],
        contactEmail: $p['contactEmail'],
        dateOrganized: $p['dateOrganized'],
        roster: $p['roster'],
    );

    expect($document->registrationDetail)->not->toBeNull();
    expect($document->registrationDetail->organization_type)->toBe(OrganizationType::CoCurricular);
    expect($document->registrationDetail->contact_person)->toBe('Student Alpha');
    expect($document->registrationDetail->roster)->toEqual(['Student Alpha', 'Student Delta']);
});

test('submission notifies both SDAO members', function () {
    $p = registrationPayload();
    $document = app(SubmitOrganizationRegistration::class)->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        organizationType: $p['organizationType'],
        description: $p['description'],
        contactPerson: $p['contactPerson'],
        contactNumber: $p['contactNumber'],
        contactEmail: $p['contactEmail'],
        dateOrganized: $p['dateOrganized'],
        roster: $p['roster'],
    );

    expect(ApprovalNotification::where('document_id', $document->id)->where('user_id', $this->sdaoA->id)->exists())->toBeTrue();
    expect(ApprovalNotification::where('document_id', $document->id)->where('user_id', $this->sdaoB->id)->exists())->toBeTrue();
});

test('affiliated secretary (equal partner) can also submit', function () {
    $p = registrationPayload();
    $document = app(SubmitOrganizationRegistration::class)->execute(
        actor: $this->studentDelta,
        organization: $this->org,
        organizationType: $p['organizationType'],
        description: $p['description'],
        contactPerson: $p['contactPerson'],
        contactNumber: $p['contactNumber'],
        contactEmail: $p['contactEmail'],
        dateOrganized: $p['dateOrganized'],
        roster: $p['roster'],
    );

    expect($document->status)->toBe(DocumentStatus::InReview);
    expect($document->submitted_by)->toBe($this->studentDelta->id);
});

test('unaffiliated user cannot submit', function () {
    $outsider = User::factory()->create();

    expect(fn () => app(SubmitOrganizationRegistration::class)->execute(
        actor: $outsider,
        organization: $this->org,
        organizationType: OrganizationType::CoCurricular,
        description: 'test',
        contactPerson: 'Test',
        contactNumber: '123',
        contactEmail: 'test@test.com',
        dateOrganized: '2020-01-01',
    ))->toThrow(AuthorizationException::class);
});
