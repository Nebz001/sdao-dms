<?php

use App\Enums\Role;
use App\Enums\ScopeType;

test('student and adviser scope to organization', function () {
    expect(Role::Student->scopeType())->toBe(ScopeType::Organization);
    expect(Role::Adviser->scopeType())->toBe(ScopeType::Organization);
});

test('program chair scopes to program', function () {
    expect(Role::ProgramChair->scopeType())->toBe(ScopeType::Program);
});

test('dean and principal scope to school', function () {
    expect(Role::Dean->scopeType())->toBe(ScopeType::School);
    expect(Role::Principal->scopeType())->toBe(ScopeType::School);
});

test('sdao member and director roles scope globally', function () {
    expect(Role::SdaoMember->scopeType())->toBe(ScopeType::Global);
    expect(Role::AssistantDirectorAcademicServices->scopeType())->toBe(ScopeType::Global);
    expect(Role::AcademicDirector->scopeType())->toBe(ScopeType::Global);
    expect(Role::ExecutiveDirector->scopeType())->toBe(ScopeType::Global);
});

test('only the three director roles report a single global holder', function () {
    expect(Role::AssistantDirectorAcademicServices->hasSingleGlobalHolder())->toBeTrue();
    expect(Role::AcademicDirector->hasSingleGlobalHolder())->toBeTrue();
    expect(Role::ExecutiveDirector->hasSingleGlobalHolder())->toBeTrue();

    // SdaoMember is also ScopeType::Global but legitimately has multiple
    // simultaneous holders — must NOT be reported as single-holder.
    expect(Role::SdaoMember->hasSingleGlobalHolder())->toBeFalse();

    // Every scoped role is also false, since the "single global holder"
    // question doesn't apply to them at all.
    expect(Role::Student->hasSingleGlobalHolder())->toBeFalse();
    expect(Role::Adviser->hasSingleGlobalHolder())->toBeFalse();
    expect(Role::ProgramChair->hasSingleGlobalHolder())->toBeFalse();
    expect(Role::Dean->hasSingleGlobalHolder())->toBeFalse();
    expect(Role::Principal->hasSingleGlobalHolder())->toBeFalse();
});

test('every role has a non-empty label', function () {
    foreach (Role::cases() as $role) {
        expect($role->label())->toBeString()->not->toBeEmpty();
    }
});

test('role enum covers all nine roles', function () {
    expect(Role::cases())->toHaveCount(9);
});
