<?php

use App\Approval\StepApproverResolver;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\Role;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\DocumentStepApproval;
use App\Models\DocumentTransition;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Models\WorkflowTemplate;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\DB;

/**
 * See app/Console/Commands/RemoveExtraSdaoMembers.php. The ids and emails are
 * the production ones the command is pinned to.
 */
beforeEach(function () {
    $this->seed(WorkflowTemplateSeeder::class);

    $make = function (int $userId, int $assignmentId, string $name, string $email) {
        $user = User::factory()->create(['id' => $userId, 'name' => $name, 'email' => $email]);
        RoleAssignment::unguarded(fn () => RoleAssignment::create([
            'id' => $assignmentId, 'user_id' => $userId, 'role' => Role::SdaoMember,
        ]));

        return $user;
    };

    $this->carl = $make(21, 21, 'Carl Justin Magpantay', 'magpantayc@nu-lipa.edu.ph');
    $this->zaira = $make(22, 22, 'Zaira Joy Enayo', 'enayoz@nu-lipa.edu.ph');
    $this->carlGmail = $make(160, 82, 'Carl Justin Magpantay', 'magpantaycarljustin@gmail.com');
    $this->memberB = $make(42, 42, 'SDAO Member B', 'sdao-b@nu-lipa.edu.ph');
    $this->sysAdmin = $make(161, 83, 'System Admin', 'ranuvirai@gmail.com');

    $template = WorkflowTemplate::where('form_type', FormType::OrganizationRegistration)->firstOrFail();
    $this->step = $template->steps()->where('role', Role::SdaoMember)->firstOrFail();

    $this->document = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'status' => DocumentStatus::InReview,
        'workflow_template_id' => $template->id,
        'current_step_position' => $this->step->position,
    ]);

    DocumentTransition::create([
        'document_id' => $this->document->id,
        'actor_id' => $this->memberB->id,
        'action' => TransitionAction::Approved,
        'from_status' => DocumentStatus::InReview,
        'to_status' => DocumentStatus::InReview,
        'step_position' => $this->step->position,
        'created_at' => now(),
    ]);
    DocumentStepApproval::create([
        'document_id' => $this->document->id,
        'workflow_step_id' => $this->step->id,
        'step_position' => $this->step->position,
        'user_id' => $this->memberB->id,
    ]);

    $this->snapshot = fn () => [
        DB::table('document_transitions')->orderBy('id')->get()->toArray(),
        DB::table('document_step_approvals')->orderBy('id')->get()->toArray(),
        DB::table('users')->whereIn('id', [21, 22, 160])->orderBy('id')->get()->toArray(),
        DB::table('role_assignments')->whereIn('id', [21, 22, 82])->orderBy('id')->get()->toArray(),
    ];
});

test('dry run is the default and writes nothing, and reports the documents waiting at SDAO', function () {
    $before = ($this->snapshot)();
    $assignments = RoleAssignment::count();

    $this->artisan('sdao:remove-extra-members')
        ->expectsOutputToContain('DRY RUN')
        ->expectsOutputToContain('1/2 approvals (1 from a removed account, still counted); needs 1 more')
        ->assertSuccessful();

    expect(RoleAssignment::count())->toBe($assignments);
    expect($this->memberB->fresh()->isDeactivated())->toBeFalse();
    expect($this->sysAdmin->fresh()->isDeactivated())->toBeFalse();
    expect(($this->snapshot)())->toEqual($before);
});

test('--dry-run together with --force still writes nothing', function () {
    $this->artisan('sdao:remove-extra-members', ['--dry-run' => true, '--force' => true])->assertSuccessful();

    expect(RoleAssignment::whereIn('id', [42, 83])->count())->toBe(2);
    expect($this->memberB->fresh()->isDeactivated())->toBeFalse();
});

test('--force removes exactly the two assignments and deactivates the two accounts', function () {
    $this->artisan('sdao:remove-extra-members', ['--force' => true])->assertSuccessful();

    expect(RoleAssignment::whereIn('id', [42, 83])->count())->toBe(0);
    expect(RoleAssignment::where('role', Role::SdaoMember)->pluck('user_id')->sort()->values()->all())->toBe([21, 22, 160]);

    // The rows are kept, only deactivated.
    expect($this->memberB->fresh()->isDeactivated())->toBeTrue();
    expect($this->sysAdmin->fresh()->isDeactivated())->toBeTrue();
    expect($this->memberB->fresh()->deactivated_reason)->not->toBeNull();
});

test('users 21, 22 and 160 are left exactly as they were', function () {
    $before = ($this->snapshot)();

    $this->artisan('sdao:remove-extra-members', ['--force' => true])->assertSuccessful();

    $after = ($this->snapshot)();
    expect($after[2])->toEqual($before[2]);
    expect($after[3])->toEqual($before[3]);
    expect($this->carl->fresh()->isDeactivated())->toBeFalse();
    expect($this->zaira->fresh()->isDeactivated())->toBeFalse();
    expect($this->carlGmail->fresh()->isDeactivated())->toBeFalse();
});

test('the transition log and step approvals are unchanged', function () {
    $before = ($this->snapshot)();

    $this->artisan('sdao:remove-extra-members', ['--force' => true])->assertSuccessful();

    $after = ($this->snapshot)();
    expect($after[0])->toEqual($before[0]);
    expect($after[1])->toEqual($before[1]);
});

test('an email mismatch stops everything and changes nothing', function () {
    $this->sysAdmin->forceFill(['email' => 'someone-else@gmail.com'])->save();
    $before = ($this->snapshot)();

    $this->artisan('sdao:remove-extra-members', ['--force' => true])
        ->expectsOutputToContain('STOPPED')
        ->assertFailed();

    // Not even the row that matched was touched.
    expect(RoleAssignment::whereIn('id', [42, 83])->count())->toBe(2);
    expect($this->memberB->fresh()->isDeactivated())->toBeFalse();
    expect(($this->snapshot)())->toEqual($before);
});

test('an assignment id that does not belong to that user stops everything', function () {
    DB::table('role_assignments')->where('id', 83)->update(['user_id' => $this->carl->id]);

    $this->artisan('sdao:remove-extra-members', ['--force' => true])->assertFailed();

    expect(RoleAssignment::whereIn('id', [42, 83])->count())->toBe(2);
    expect($this->memberB->fresh()->isDeactivated())->toBeFalse();
});

test('a missing row stops everything', function () {
    RoleAssignment::whereKey(42)->delete();

    $this->artisan('sdao:remove-extra-members', ['--force' => true])->assertFailed();

    expect(RoleAssignment::whereKey(83)->exists())->toBeTrue();
    expect($this->sysAdmin->fresh()->isDeactivated())->toBeFalse();
});

test('it refuses when too few SDAO members would remain', function () {
    RoleAssignment::whereKey(82)->delete();
    RoleAssignment::whereKey(22)->delete();

    $this->artisan('sdao:remove-extra-members', ['--force' => true])
        ->expectsOutputToContain('the SDAO step needs 2')
        ->assertFailed();

    expect(RoleAssignment::whereIn('id', [42, 83])->count())->toBe(2);
});

test('after the change the SDAO step resolves exactly the remaining members', function () {
    $this->artisan('sdao:remove-extra-members', ['--force' => true])->assertSuccessful();

    $approvers = app(StepApproverResolver::class)->approversFor($this->step, $this->document->fresh());

    expect($approvers->pluck('id')->sort()->values()->all())->toBe([21, 22, 160]);
});
