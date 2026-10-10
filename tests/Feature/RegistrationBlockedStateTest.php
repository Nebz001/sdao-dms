<?php

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\WorkflowTemplateSeeder;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * registrations/create receives a `blocked` prop that only DESCRIBES the rules
 * the server already enforces (DocumentPolicy::propose and the one-registration-
 * in-flight guard in SubmitOrganizationRegistration), so the page can show why
 * the form is closed. It never decides who is blocked.
 */
beforeEach(function () {
    $this->withoutVite();
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
});

function registrationCreatePage(User $user)
{
    return test()->actingAs($user)->get(route('registrations.create'));
}

test('a verified student with nothing in flight gets the form, with no blocked state', function () {
    $student = User::factory()->create(['account_status' => 'verified']);

    registrationCreatePage($student)->assertOk()->assertInertia(fn ($page) => $page
        ->component('registrations/create')
        ->where('canPropose', true)
        ->where('blocked', null));
});

test('an unverified student is blocked as unverified', function () {
    $student = User::factory()->create(['account_status' => 'unverified']);

    registrationCreatePage($student)->assertOk()->assertInertia(fn ($page) => $page
        ->where('canPropose', false)
        ->where('blocked.reason', 'unverified'));
});

test('an active officer is blocked as an officer, with the organization and the year it covers', function () {
    $officer = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();

    registrationCreatePage($officer)->assertOk()->assertInertia(fn ($page) => $page
        ->where('canPropose', false)
        ->where('blocked.reason', 'officer')
        ->where('blocked.organization', 'Computing Society')
        ->has('blocked.covered_year'));
});

test('a student with a registration still in review is blocked, linking to that document', function () {
    $student = User::factory()->create(['account_status' => 'verified']);
    $organization = Organization::where('name', 'IT Guild')->firstOrFail();

    $document = Document::create([
        'form_type' => FormType::OrganizationRegistration,
        'variant' => null,
        'title' => 'Registration — IT Guild',
        'status' => DocumentStatus::InReview,
        'current_step_position' => 1,
        'organization_id' => $organization->id,
        'workflow_template_id' => null,
        'submitted_by' => $student->id,
    ]);

    registrationCreatePage($student)->assertOk()->assertInertia(fn ($page) => $page
        ->where('blocked.reason', 'in_review')
        ->where('blocked.organization', 'IT Guild')
        ->where('blocked.document_id', $document->id)
        ->where('blocked.href', route('registrations.show', $document)));
});

test('a rejected registration no longer blocks a new one', function () {
    $student = User::factory()->create(['account_status' => 'verified']);
    $organization = Organization::where('name', 'IT Guild')->firstOrFail();

    Document::create([
        'form_type' => FormType::OrganizationRegistration,
        'variant' => null,
        'title' => 'Registration — IT Guild',
        'status' => DocumentStatus::Rejected,
        'current_step_position' => null,
        'organization_id' => $organization->id,
        'workflow_template_id' => null,
        'submitted_by' => $student->id,
    ]);

    registrationCreatePage($student)->assertOk()->assertInertia(fn ($page) => $page->where('blocked', null));
});
