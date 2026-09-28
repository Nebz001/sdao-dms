<?php

use App\Models\Organization;
use App\Models\School;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * An Academic (Co-Curricular) organization registration may only choose a
 * school from the closed allow-list of 4 real schools — School::
 * scopeAcademicRegistrationChoices(), driven by the `academic_rank` column.
 * This is the single source both the registration page's 'schools' prop and
 * StoreRegistrationRequest's validation read from, so they cannot disagree.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
});

test('School::academicRegistrationChoices returns exactly the 4 ranked schools in rank order', function () {
    // IdentitySeeder ranks its 4 placeholder schools 1-4 (same conceptual
    // slots as the real SACE/SABM/SAHS/SHS roster) and leaves any other
    // school unranked.
    School::factory()->create(['academic_rank' => null]);

    $names = School::academicRegistrationChoices()->pluck('name');

    expect($names)->toHaveCount(4);
    expect($names->all())->toBe([
        'School of Computing and IT',
        'School of Business and Accountancy',
        'School of Health Sciences',
        'Senior High School',
    ]);
});

test('the registration page only offers the 4 ranked schools, in rank order', function () {
    $student = User::factory()->create();

    $response = $this->actingAs($student)->get(route('registrations.create'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('schools', fn ($schools) => collect($schools)->pluck('name')->all() === [
            'School of Computing and IT',
            'School of Business and Accountancy',
            'School of Health Sciences',
            'Senior High School',
        ])
    );
});

test('store rejects a school outside the academic allow-list, even though the row exists', function () {
    $student = User::factory()->create();
    $outsideSchool = School::factory()->create(['academic_rank' => null]);

    $response = $this->actingAs($student)->post(route('registrations.store'), array_merge(
        foundingRegistrationPayload([
            'school_id' => $outsideSchool->id,
            'adviser_id' => unboundAdviserForAttachmentsTest()->id,
        ]),
        ['attachments' => registrationAttachmentFiles()],
    ));

    $response->assertInvalid(['school_id']);
    expect(Organization::where('name', 'Attachments Test Org')->exists())->toBeFalse();
});

test('store accepts a school inside the academic allow-list', function () {
    $student = User::factory()->create();
    $school = School::where('name', 'School of Computing and IT')->firstOrFail();
    $program = $school->programs()->firstOrFail();

    $response = $this->actingAs($student)->post(route('registrations.store'), array_merge(
        foundingRegistrationPayload([
            'school_id' => $school->id,
            'program_id' => $program->id,
            'adviser_id' => unboundAdviserForAttachmentsTest()->id,
        ]),
        ['attachments' => registrationAttachmentFiles()],
    ));

    $response->assertSessionHasNoErrors();
    expect(Organization::where('name', 'Attachments Test Org')->where('school_id', $school->id)->exists())->toBeTrue();
});

test('the registration page still loads with an empty college list if no school is ranked', function () {
    School::query()->update(['academic_rank' => null]);
    $student = User::factory()->create();

    $response = $this->actingAs($student)->get(route('registrations.create'));

    $response->assertOk()->assertInertia(fn ($page) => $page->where('schools', []));
});

test('an Extra-Curricular registration still needs no school at all', function () {
    $student = User::factory()->create();

    $response = $this->actingAs($student)->post(route('registrations.store'), array_merge(
        foundingRegistrationPayload(['adviser_id' => unboundAdviserForAttachmentsTest()->id]),
        ['attachments' => registrationAttachmentFiles()],
    ));

    $response->assertSessionHasNoErrors();
    expect(Organization::where('name', 'Attachments Test Org')->where('school_id', null)->exists())->toBeTrue();
});
