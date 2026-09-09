<?php

use App\Enums\OrganizationType;
use App\Models\Organization;

/**
 * Structural fix (2026-09-09 plan) — organization_type is derived from
 * school_id, never independently stored/settable input. These pin the single
 * derivation point every write site (Submit/UpdateOrganizationRegistration,
 * Submit/UpdateOrganizationRenewal) goes through.
 */
test('fromSchoolId returns CoCurricular for a non-null school id', function () {
    expect(OrganizationType::fromSchoolId(5))->toBe(OrganizationType::CoCurricular);
});

test('fromSchoolId returns ExtraCurricular for a null school id', function () {
    expect(OrganizationType::fromSchoolId(null))->toBe(OrganizationType::ExtraCurricular);
});

test('fromOrganization derives from the organization\'s school_id', function () {
    // Bare, unsaved instances — no factory() here deliberately: it always
    // creates a real Program (and thus a real School) via ->create(), which
    // needs a DB connection tests/Unit doesn't have.
    $withSchool = new Organization(['school_id' => 5]);
    $withoutSchool = new Organization(['school_id' => null]);

    expect(OrganizationType::fromOrganization($withSchool))->toBe(OrganizationType::CoCurricular);
    expect(OrganizationType::fromOrganization($withoutSchool))->toBe(OrganizationType::ExtraCurricular);
});
