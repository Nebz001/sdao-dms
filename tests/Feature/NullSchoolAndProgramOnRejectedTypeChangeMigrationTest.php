<?php

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\Program;

/**
 * Regression coverage for the 2026_09_10_092831 migration. The prior
 * migration (2026_09_09_100000) picked each org's most-recent registration/
 * renewal detail row "of any status" to decide organization_type — too
 * broad, because a Rejected document never took effect. This is the exact
 * shape that slipped through for Red Cross Youth: an Approved
 * extra_curricular registration, followed later by a Rejected co_curricular
 * renewal. The Rejected row being more recent must not leave the org
 * routable to a program chair it no longer has.
 */
function runNullSchoolAndProgramOnRejectedTypeChangeMigration(): void
{
    (require database_path('migrations/2026_09_10_092831_null_school_and_program_on_rejected_type_change.php'))->up();
}

test('a rejected renewal does not outrank an earlier approved registration when deriving organization_type', function () {
    $program = Program::factory()->create();
    $org = Organization::factory()->create([
        'school_id' => $program->school_id,
        'program_id' => $program->id,
    ]);

    $approvedRegistration = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'status' => DocumentStatus::Approved,
        'organization_id' => $org->id,
    ]);
    OrganizationRegistrationDetail::factory()->create([
        'document_id' => $approvedRegistration->id,
        'organization_type' => OrganizationType::ExtraCurricular,
    ]);

    $rejectedRenewal = Document::factory()->create([
        'form_type' => FormType::OrganizationRenewal,
        'status' => DocumentStatus::Rejected,
        'organization_id' => $org->id,
        'created_at' => $approvedRegistration->created_at->addDay(),
    ]);
    OrganizationRegistrationDetail::factory()->create([
        'document_id' => $rejectedRenewal->id,
        'organization_type' => OrganizationType::CoCurricular,
    ]);

    runNullSchoolAndProgramOnRejectedTypeChangeMigration();

    $org->refresh();
    expect($org->school_id)->toBeNull();
    expect($org->program_id)->toBeNull();
});

test('an in-review renewal still outranks an older approved registration when deriving organization_type', function () {
    $program = Program::factory()->create();
    $org = Organization::factory()->create([
        'school_id' => $program->school_id,
        'program_id' => $program->id,
    ]);

    $approvedRegistration = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'status' => DocumentStatus::Approved,
        'organization_id' => $org->id,
    ]);
    OrganizationRegistrationDetail::factory()->create([
        'document_id' => $approvedRegistration->id,
        'organization_type' => OrganizationType::ExtraCurricular,
    ]);

    $inReviewRenewal = Document::factory()->create([
        'form_type' => FormType::OrganizationRenewal,
        'status' => DocumentStatus::InReview,
        'organization_id' => $org->id,
        'created_at' => $approvedRegistration->created_at->addDay(),
    ]);
    OrganizationRegistrationDetail::factory()->create([
        'document_id' => $inReviewRenewal->id,
        'organization_type' => OrganizationType::CoCurricular,
    ]);

    runNullSchoolAndProgramOnRejectedTypeChangeMigration();

    $org->refresh();
    expect($org->school_id)->not->toBeNull();
    expect($org->program_id)->not->toBeNull();
});

test('an org whose only extra-curricular record is rejected is left untouched', function () {
    $program = Program::factory()->create();
    $org = Organization::factory()->create([
        'school_id' => $program->school_id,
        'program_id' => $program->id,
    ]);

    $rejectedRegistration = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'status' => DocumentStatus::Rejected,
        'organization_id' => $org->id,
    ]);
    OrganizationRegistrationDetail::factory()->create([
        'document_id' => $rejectedRegistration->id,
        'organization_type' => OrganizationType::ExtraCurricular,
    ]);

    runNullSchoolAndProgramOnRejectedTypeChangeMigration();

    $org->refresh();
    expect($org->school_id)->not->toBeNull();
    expect($org->program_id)->not->toBeNull();
});
