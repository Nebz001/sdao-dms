<?php

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\Role;
use App\Models\Document;
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

test('the dry run lists the accounts, what is attached and every check, and changes nothing', function () {
    $document = sdaoCleanupRegistration();

    foreach ([$this->sdaoA, $this->sdaoB, $this->carl, $this->zaira] as $user) {
        sdaoCleanupNotify($document, $user);
    }

    $users = User::count();
    $notifications = DB::table('notifications')->count();

    $lines = implode("\n", removePlaceholderSdaoMigration()->dryRun());

    expect($lines)->toContain('DRY RUN')
        ->toContain("#{$this->sdaoA->id} sdao-a@nu-lipa.edu.ph")
        ->toContain("#{$this->sdaoB->id} sdao-b@nu-lipa.edu.ph")
        ->toContain('magpantayc@nu-lipa.edu.ph')
        ->toContain('2 approver notifications')
        ->toContain('RESULT: all checks pass')
        ->and(User::count())->toBe($users)
        ->and(DB::table('notifications')->count())->toBe($notifications);
});

test('the migration refuses, and changes nothing, once a placeholder has acted on a document', function () {
    $document = sdaoCleanupRegistration();
    DocumentTransition::factory()->create(['document_id' => $document->id, 'actor_id' => $this->sdaoA->id, 'created_at' => now()]);
    $users = User::count();

    expect(fn () => removePlaceholderSdaoMigration()->up())->toThrow(RuntimeException::class, 'transitions by the removed accounts')
        ->and(User::count())->toBe($users)
        ->and(implode("\n", removePlaceholderSdaoMigration()->dryRun()))->toContain('RESULT: BLOCKED');
});

test('the migration refuses when a placeholder was notified about a document no real SDAO member was', function () {
    sdaoCleanupNotify(sdaoCleanupRegistration(), $this->sdaoA);
    $users = User::count();

    expect(fn () => removePlaceholderSdaoMigration()->up())->toThrow(RuntimeException::class, 'notifications only the removed accounts received')
        ->and(User::count())->toBe($users);
});

test('the migration refuses unless two other SDAO members remain', function () {
    RoleAssignment::where('user_id', $this->zaira->id)->where('role', Role::SdaoMember->value)->delete();
    $users = User::count();

    expect(fn () => removePlaceholderSdaoMigration()->up())->toThrow(RuntimeException::class, 'fewer than two other SDAO members')
        ->and(User::count())->toBe($users);
});

test('the migration never touches an account on a placeholder email whose name differs', function () {
    $this->sdaoA->update(['name' => 'A Real Person']);

    removePlaceholderSdaoMigration()->up();

    expect(User::find($this->sdaoA->id))->not->toBeNull()
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
