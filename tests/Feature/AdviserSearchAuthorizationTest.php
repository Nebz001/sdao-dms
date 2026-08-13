<?php

use App\Approval\ApprovalEngine;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Security gap fix: RegistrationController::adviserSearch() previously had no
 * authorization call at all — the only method on the class without one. Any
 * authenticated, email-verified account (including one still awaiting SDAO
 * account verification) could enumerate every adviser's name and email, with
 * no search term required. The fix reuses Gate::authorize('propose', ...) —
 * the same ability store() already enforces on the submission this typeahead
 * feeds — rather than inventing a new one.
 *
 * Known, accepted residual (see the plan): propose() only checks
 * OrganizationMembership, not RoleAssignment, so SDAO/approver accounts are
 * NOT excluded by this gate — that's why the existing
 * RegistrationAdviserValidationTest / OrganizationFoundingTest tests (bare
 * factory users and sdaoA) keep passing unchanged. These tests pin the
 * boundary this fix DOES enforce: SDAO-unverified accounts and
 * already-affiliated students are blocked; a genuinely eligible student
 * (fresh, or mid-resubmission after a return) still works.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->school = School::where('name', 'School of Computing and IT')->firstOrFail();
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
});

function adviserSearchFoundingPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Adviser Search Test Org',
        'organization_type' => 'co_curricular',
        'purpose_of_organization' => 'Testing the adviser search gate.',
        'contact_person' => 'Contact Person',
        'contact_no' => '09170000000',
        'email_address' => 'contact@example.test',
        'date_organized' => '2020-06-01',
    ], $overrides);
}

test('an already-affiliated student cannot use the adviser search', function () {
    // student-alpha is Computing Society's seeded active President
    // (MembershipSeeder) — hasActiveMembershipElsewhere() is true, so
    // propose() denies.
    $studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();

    $this->actingAs($studentAlpha)
        ->getJson(route('registrations.adviser-search', ['q' => 'Adviser']))
        ->assertForbidden();
});

test('a self-registered account still awaiting SDAO verification cannot use the adviser search', function () {
    $unverified = User::factory()->unverifiedAccount()->create();

    $this->actingAs($unverified)
        ->getJson(route('registrations.adviser-search', ['q' => 'Adviser']))
        ->assertForbidden();
});

test('a request with no q is forbidden before ever reaching the query, not just filtered', function () {
    $unverified = User::factory()->unverifiedAccount()->create();

    // No search term at all — the empty-query free-harvest path this gap
    // originally allowed is now behind the same gate.
    $this->actingAs($unverified)
        ->getJson(route('registrations.adviser-search'))
        ->assertForbidden();
});

test('a genuinely propose-eligible student still gets real adviser search results', function () {
    $adviser = User::factory()->create(['name' => 'Searchable Adviser']);
    RoleAssignment::create(['user_id' => $adviser->id, 'role' => Role::Adviser->value]);

    $freshStudent = User::factory()->create();

    $response = $this->actingAs($freshStudent)
        ->getJson(route('registrations.adviser-search', ['q' => 'Searchable']));

    $response->assertOk();
    expect(collect($response->json('advisers'))->pluck('id'))->toContain($adviser->id);
});

test('a student mid-resubmission of a Returned registration still gets real adviser search results', function () {
    $adviser = User::factory()->create(['name' => 'Resubmit Flow Adviser']);
    RoleAssignment::create(['user_id' => $adviser->id, 'role' => Role::Adviser->value]);

    $founder = User::factory()->create();

    $this->actingAs($founder)->post(route('registrations.store'), array_merge(
        adviserSearchFoundingPayload(['school_id' => $this->school->id, 'adviser_id' => $adviser->id]),
        ['attachments' => registrationAttachmentFiles()],
    ));

    $document = Document::where('title', 'like', '%Adviser Search Test Org%')->firstOrFail();
    $this->engine->returnForRevision($document, $this->sdaoA, 'Please double-check the adviser.');
    $document->refresh();
    expect($document->status->value)->toBe('returned');

    // The founder has no OrganizationMembership yet (only bound at approval),
    // so propose() still holds while they edit and resubmit.
    $response = $this->actingAs($founder)
        ->getJson(route('registrations.adviser-search', ['q' => 'Resubmit Flow']));

    $response->assertOk();
    expect(collect($response->json('advisers'))->pluck('id'))->toContain($adviser->id);
});
