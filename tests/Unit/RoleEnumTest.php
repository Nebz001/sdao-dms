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

test('every role has a non-empty label', function () {
    foreach (Role::cases() as $role) {
        expect($role->label())->toBeString()->not->toBeEmpty();
    }
});

test('role enum covers all nine roles', function () {
    expect(Role::cases())->toHaveCount(9);
});
