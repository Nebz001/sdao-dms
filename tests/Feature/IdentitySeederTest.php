<?php

use App\Enums\Role;
use App\Models\Organization;
use App\Models\Program;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use Database\Seeders\IdentitySeeder;

test('IdentitySeeder creates no school, program, organization, student or director', function () {
    $this->seed(IdentitySeeder::class);

    expect(School::count())->toBe(0)
        ->and(Program::count())->toBe(0)
        ->and(Organization::count())->toBe(0)
        ->and(User::where('email', 'like', '%@students.%')->exists())->toBeFalse()
        ->and(RoleAssignment::whereIn('role', [
            Role::Student->value,
            Role::Dean->value,
            Role::ProgramChair->value,
            Role::Principal->value,
            Role::AssistantDirectorAcademicServices->value,
            Role::AcademicDirector->value,
            Role::ExecutiveDirector->value,
        ])->exists())->toBeFalse();
});

test('IdentitySeeder provisions the unassigned demo adviser pool and no SDAO account', function () {
    $this->seed(IdentitySeeder::class);

    expect(RoleAssignment::where('role', Role::SdaoMember->value)->exists())->toBeFalse()
        ->and(User::where('email', 'like', 'sdao-%')->exists())->toBeFalse();

    $pool = ['adviser-one@nu-lipa.edu.ph', 'adviser-two@nu-lipa.edu.ph', 'adviser-shs@nu-lipa.edu.ph'];

    expect(User::whereIn('email', $pool)->count())->toBe(3)
        ->and(RoleAssignment::where('role', Role::Adviser->value)->whereNull('organization_id')
            ->whereHas('user', fn ($q) => $q->whereIn('email', $pool))->count())->toBe(3);
});
