<?php

use App\Models\Program;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Models\WorkflowTemplate;
use Database\Seeders\RealRosterSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Regression coverage for the production incident where a retried
 * `db:seed` (after a partial run had already inserted rows) crashed on
 * workflow_templates' (form_type, variant) unique constraint and would
 * have silently duplicated users/schools/programs/role_assignments had it
 * not crashed. These seeders must be safe to run any number of times.
 */
$counts = fn () => [
    Setting::count(),
    User::count(),
    School::count(),
    Program::count(),
    RoleAssignment::count(),
    WorkflowTemplate::count(),
    WorkflowStep::count(),
];

test('rerunning the production seeders is a no-op, not a duplicate insert', function () use ($counts) {
    $seeders = [SettingsSeeder::class, RealRosterSeeder::class, WorkflowTemplateSeeder::class];

    $this->seed($seeders);
    $firstRun = $counts();

    // Rerun three times — simulates the exact production scenario of a
    // failed/retried db:seed invocation.
    $this->seed($seeders);
    $this->seed($seeders);
    $this->seed($seeders);

    expect($counts())->toBe($firstRun);
    // 4 short chains + 6 proposal variants (Regular/SHS/ExtraCurricular ×
    // on/off-calendar — Phase 2 remediation item 3 added the latter pair).
    expect(WorkflowTemplate::count())->toBe(10);
    expect(WorkflowStep::count())->toBe(40);
    expect(User::count())->toBe(20);
    expect(RoleAssignment::count())->toBe(20);
});
