<?php

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\Role;
use App\Models\Document;
use App\Models\DocumentStepApproval;
use App\Models\DocumentTransition;
use App\Models\RoleAssignment;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\RealRosterSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

function removePlaceholderSdaoMigration(): object
{
    return require database_path('migrations/2026_10_06_100000_remove_placeholder_sdao_accounts.php');
}

/** A dev database as it was: the real SDAO members plus the two placeholders IdentitySeeder used to add. */
function oldPlaceholderSdao(): array
{
    $accounts = [];

    foreach ([['SDAO Member A', 'sdao-a@nu-lipa.edu.ph'], ['SDAO Member B', 'sdao-b@nu-lipa.edu.ph']] as [$name, $email]) {
        $user = User::factory()->create(['name' => $name, 'email' => $email, 'password' => Hash::make('ict@1234')]);
        RoleAssignment::create(['user_id' => $user->id, 'role' => Role::SdaoMember]);
        $accounts[] = $user;
    }

    return $accounts;
}

/** The demo hand-off: an SDAO member is notified about the document, in both notification tables. */
function sdaoCleanupNotify(Document $document, User $user): void
{
    DB::table('approval_notifications')->insert(['document_id' => $document->id, 'user_id' => $user->id, 'step_position' => 1, 'created_at' => now()]);
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => 'approver_hand_off',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode(['kind' => 'approver_hand_off', 'document_id' => $document->id]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function sdaoCleanupRegistration(): Document
{
    return Document::factory()->create(['form_type' => FormType::OrganizationRegistration, 'status' => DocumentStatus::InReview]);
}

beforeEach(function () {
    $this->seed([SettingsSeeder::class, RealRosterSeeder::class, WorkflowTemplateSeeder::class, IdentitySeeder::class]);
    [$this->sdaoA, $this->sdaoB] = oldPlaceholderSdao();
    $this->carl = User::where('email', 'magpantayc@nu-lipa.edu.ph')->firstOrFail();
    $this->zaira = User::where('email', 'enayoz@nu-lipa.edu.ph')->firstOrFail();
});

test('IdentitySeeder no longer creates SDAO accounts: the real members are the only two a dev database has', function () {
    $this->sdaoA->delete();
    $this->sdaoB->delete();

    $sdao = User::whereIn('id', RoleAssignment::where('role', Role::SdaoMember->value)->pluck('user_id'))->pluck('email')->all();

    expect($sdao)->toEqualCanonicalizing(['magpantayc@nu-lipa.edu.ph', 'enayoz@nu-lipa.edu.ph']);
});

test('the migration removes both placeholders, their seats and their duplicate notifications, and keeps every real record', function () {
    $document = sdaoCleanupRegistration();

    foreach ([$this->sdaoA, $this->sdaoB, $this->carl, $this->zaira] as $user) {
        sdaoCleanupNotify($document, $user);
    }

    $users = User::count();
    $seats = RoleAssignment::count();

    removePlaceholderSdaoMigration()->up();

    expect(User::whereIn('email', ['sdao-a@nu-lipa.edu.ph', 'sdao-b@nu-lipa.edu.ph'])->exists())->toBeFalse()
        ->and(User::count())->toBe($users - 2)
        ->and(RoleAssignment::count())->toBe($seats - 2)
        ->and(DB::table('approval_notifications')->whereIn('user_id', [$this->sdaoA->id, $this->sdaoB->id])->exists())->toBeFalse()
        ->and(DB::table('notifications')->whereIn('notifiable_id', [$this->sdaoA->id, $this->sdaoB->id])->exists())->toBeFalse()
        // The real SDAO members keep their seats and their notifications.
        ->and(RoleAssignment::where('role', Role::SdaoMember->value)->count())->toBe(2)
        ->and(DB::table('approval_notifications')->whereIn('user_id', [$this->carl->id, $this->zaira->id])->count())->toBe(2)
        ->and(DB::table('notifications')->whereIn('notifiable_id', [$this->carl->id, $this->zaira->id])->count())->toBe(2);
});

test('the dry run says what it would delete, deactivate and skip, and changes nothing', function () {
    $document = sdaoCleanupRegistration();
    DocumentTransition::factory()->create(['document_id' => $document->id, 'actor_id' => $this->sdaoB->id, 'created_at' => now()]);
    $this->sdaoA->forceFill(['deactivated_at' => now()])->save();
    DocumentTransition::factory()->create(['document_id' => $document->id, 'actor_id' => $this->sdaoA->id, 'created_at' => now()]);
    $users = User::count();

    $lines = implode('
', removePlaceholderSdaoMigration()->dryRun());

    expect($lines)->toContain('DRY RUN')
        ->toContain('WOULD DEACTIVATE (1)')
        ->toContain("#{$this->sdaoB->id} sdao-b@nu-lipa.edu.ph")
        ->toContain('WOULD SKIP (1)')
        ->toContain('magpantayc@nu-lipa.edu.ph')
        ->toContain('Safety check passed')
        ->and(User::count())->toBe($users)
        ->and($this->sdaoB->fresh()->deactivated_at)->toBeNull();
});

test('a placeholder with attached work is deactivated, kept, and its history is left exactly as it was', function () {
    $document = sdaoCleanupRegistration();
    $transition = DocumentTransition::factory()->create(['document_id' => $document->id, 'actor_id' => $this->sdaoA->id, 'created_at' => now()]);
    DocumentStepApproval::factory()->create(['document_id' => $document->id, 'user_id' => $this->sdaoA->id]);
    config(['session.driver' => 'database']);
    DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $this->sdaoA->id, 'payload' => 'x', 'last_activity' => now()->timestamp]);
    $transitions = DB::table('document_transitions')->get()->toArray();
    $approvals = DB::table('document_step_approvals')->get()->toArray();
    $users = User::count();

    removePlaceholderSdaoMigration()->up();

    $kept = $this->sdaoA->fresh();

    expect(User::count())->toBe($users - 1) // only the clean placeholder (B) is deleted
        ->and($kept)->not->toBeNull()
        ->and($kept->isDeactivated())->toBeTrue()
        ->and($kept->deactivated_reason)->toContain('Placeholder SDAO account retired')
        ->and(DB::table('sessions')->where('user_id', $kept->id)->exists())->toBeFalse()
        // Append only: not a single history row was edited, moved or removed.
        ->and(DB::table('document_transitions')->get()->toArray())->toEqual($transitions)
        ->and(DB::table('document_step_approvals')->get()->toArray())->toEqual($approvals)
        ->and(DocumentTransition::find($transition->id)->actor_id)->toBe($this->sdaoA->id);
});

test('a placeholder that is already deactivated and has history is left exactly as it is', function () {
    DocumentTransition::factory()->create(['document_id' => sdaoCleanupRegistration()->id, 'actor_id' => $this->sdaoA->id, 'created_at' => now()]);
    $at = now()->subDays(5)->startOfSecond();
    $this->sdaoA->forceFill(['deactivated_at' => $at, 'deactivated_reason' => 'Earlier reason'])->save();

    removePlaceholderSdaoMigration()->up();

    $kept = $this->sdaoA->fresh();

    expect($kept->deactivated_at->equalTo($at))->toBeTrue()
        ->and($kept->deactivated_reason)->toBe('Earlier reason');
});

test('with no active real SDAO account nothing changes and nothing is thrown', function () {
    DocumentTransition::factory()->create(['document_id' => sdaoCleanupRegistration()->id, 'actor_id' => $this->sdaoA->id, 'created_at' => now()]);
    // Both real members are unable to sign in: one deactivated, one never verified.
    $this->carl->forceFill(['deactivated_at' => now()])->save();
    $this->zaira->forceFill(['account_status' => 'unverified'])->save();
    $users = User::count();
    Log::spy();

    removePlaceholderSdaoMigration()->up();

    expect(User::count())->toBe($users)
        ->and($this->sdaoA->fresh()->isDeactivated())->toBeFalse()
        ->and($this->sdaoB->fresh())->not->toBeNull()
        ->and($this->sdaoB->fresh()->isDeactivated())->toBeFalse()
        ->and(implode('
', removePlaceholderSdaoMigration()->dryRun()))->toContain('SAFETY CHECK FAILED');

    Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'Skipped ALL placeholder SDAO accounts'))->once();
});

test('every action is written to the log', function () {
    DocumentTransition::factory()->create(['document_id' => sdaoCleanupRegistration()->id, 'actor_id' => $this->sdaoA->id, 'created_at' => now()]);
    Log::spy();

    removePlaceholderSdaoMigration()->up();

    Log::shouldHaveReceived('info')->withArgs(fn ($m) => str_contains($m, 'Deactivated #'.$this->sdaoA->id))->once();
    Log::shouldHaveReceived('info')->withArgs(fn ($m) => str_contains($m, 'Deleted #'.$this->sdaoB->id))->once();
});

test('the migration never touches an account on a placeholder email whose name differs', function () {
    $this->sdaoA->update(['name' => 'A Real Person']);

    removePlaceholderSdaoMigration()->up();

    expect(User::find($this->sdaoA->id))->not->toBeNull()
        ->and($this->sdaoA->fresh()->isDeactivated())->toBeFalse()
        ->and(User::find($this->sdaoB->id))->toBeNull();
});

test('the migration is a no-op on a database that has no placeholder SDAO accounts', function () {
    $this->sdaoA->delete();
    $this->sdaoB->delete();
    RoleAssignment::query()->delete();
    $users = User::count();

    removePlaceholderSdaoMigration()->up();

    expect(User::count())->toBe($users);
});
