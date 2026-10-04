<?php

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OfficerChangeRequestStatus;
use App\Enums\Role;
use App\Models\Document;
use App\Models\OfficerChangeRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationRegistrationDetail;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Models\WorkflowTemplate;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * REAL two-connection races for the officer seat locks
 * (OrganizationMembershipService::runSeatChange). The in-memory SQLite suite
 * cannot show these: lockForUpdate() is a no-op there and a second process
 * can't even see the first's data. Same opt-in rules and the same dedicated
 * `_test` Postgres database as Approval/ConcurrentApprovalRaceTest — it NEVER
 * touches the development database:
 *
 *   RUN_DB_RACE_TESTS=1 RACE_TEST_DB_DATABASE=sdao_dms_test \
 *     php artisan test --compact tests/Feature/Organizations/OfficerSeatRaceTest.php
 *
 * Each scenario starts process A, which performs its change and then HOLDS its
 * transaction (and so the locks) open for a few seconds, touching a marker
 * file once it is inside the lock. Only then does process B start — so B
 * provably arrives while A is mid-transaction. Fixtures are real committed
 * rows on the test database, prefixed RACE-SEAT / race-seat- and deleted in
 * afterEach (and swept in beforeEach in case an earlier run crashed).
 */
const SEAT_RACE_HOLD_SECONDS = 4;

/**
 * @return array{env: array<string, string>|null, reason: string|null}
 */
function seatRaceDatabaseEnv(): array
{
    if (getenv('RUN_DB_RACE_TESTS') !== '1') {
        return ['env' => null, 'reason' => 'Skipped: needs a dedicated Postgres test database. Set RUN_DB_RACE_TESTS=1 and RACE_TEST_DB_DATABASE to a name ending in _test. It never touches the development database.'];
    }

    $database = (string) getenv('RACE_TEST_DB_DATABASE');

    if (! str_ends_with($database, '_test')) {
        return ['env' => null, 'reason' => 'Skipped: RACE_TEST_DB_DATABASE must be a database whose name ends in _test.'];
    }

    $appDatabase = (string) (Dotenv\Dotenv::createArrayBacked(base_path())->safeLoad()['DB_DATABASE'] ?? '');

    if ($appDatabase !== '' && $appDatabase === $database) {
        return ['env' => null, 'reason' => 'Skipped: RACE_TEST_DB_DATABASE is the same database .env points at.'];
    }

    return ['env' => [
        'DB_CONNECTION' => 'pgsql',
        'DB_URL' => '',
        'DB_HOST' => getenv('RACE_TEST_DB_HOST') ?: '127.0.0.1',
        'DB_PORT' => getenv('RACE_TEST_DB_PORT') ?: '5432',
        'DB_DATABASE' => $database,
        'DB_USERNAME' => getenv('RACE_TEST_DB_USERNAME') ?: 'postgres',
        'DB_PASSWORD' => (string) getenv('RACE_TEST_DB_PASSWORD'),
    ], 'reason' => null];
}

/**
 * Child-process script: runs $body inside a transaction that HOLDS open for
 * $hold seconds after touching $marker (when given), reporting OK/REFUSED.
 */
function seatRaceScript(string $body, int $hold = 0, ?string $marker = null): string
{
    $markerPhp = $marker !== null ? "touch('".addslashes($marker)."');" : '';

    return <<<PHP
        if (! str_ends_with(\Illuminate\Support\Facades\DB::connection()->getDatabaseName(), '_test')) {
            throw new \RuntimeException('Refusing to run: the database name does not end in _test.');
        }
        try {
            \Illuminate\Support\Facades\DB::transaction(function () {
                {$body}
                {$markerPhp}
                if ({$hold} > 0) { sleep({$hold}); }
            });
            echo 'RESULT:OK';
        } catch (\Illuminate\Validation\ValidationException \$e) {
            echo 'RESULT:REFUSED:'.json_encode(\$e->errors());
        } catch (\Throwable \$e) {
            echo 'RESULT:ERROR:'.get_class(\$e).':'.\$e->getMessage();
        }
        PHP;
}

function seatRaceProcess(array $env, string $script): Process
{
    return new Process(['php', 'artisan', 'tinker', '--execute='.$script], base_path(), $env);
}

function seatRaceResult(Process $process): string
{
    preg_match('/RESULT:(.*)/', $process->getOutput(), $m);

    return isset($m[1]) ? trim($m[1]) : 'NO RESULT: '.$process->getOutput().$process->getErrorOutput();
}

function waitForMarker(string $marker, Process $holder): void
{
    $deadline = microtime(true) + 40;

    while (! file_exists($marker)) {
        if (! $holder->isRunning() || microtime(true) > $deadline) {
            throw new RuntimeException('Process A never reached its lock: '.$holder->getOutput().$holder->getErrorOutput());
        }
        usleep(100_000);
    }
}

function sweepSeatRaceFixtures(): void
{
    $db = DB::connection('pgsql');
    $orgIds = $db->table('organizations')->where('name', 'like', 'RACE-SEAT %')->pluck('id');
    $userIds = $db->table('users')->where('email', 'like', 'race-seat-%')->pluck('id');
    $docIds = $db->table('documents')->whereIn('organization_id', $orgIds)->pluck('id');

    $db->table('document_step_approvals')->whereIn('document_id', $docIds)->delete();
    $db->table('document_transitions')->whereIn('document_id', $docIds)->delete();
    $db->table('organization_registration_details')->whereIn('document_id', $docIds)->delete();
    $db->table('documents')->whereIn('id', $docIds)->delete();
    $db->table('officer_change_requests')->whereIn('organization_id', $orgIds)->delete();
    $db->table('organization_memberships')->whereIn('organization_id', $orgIds)->delete();
    $db->table('notifications')->whereIn('notifiable_id', $userIds)->delete();
    $db->table('role_assignments')->whereIn('user_id', $userIds)->delete();
    $db->table('organizations')->whereIn('id', $orgIds)->delete();
    $db->table('users')->whereIn('id', $userIds)->delete();
}

beforeEach(function () {
    $settings = seatRaceDatabaseEnv();

    if ($settings['env'] === null) {
        $this->markTestSkipped($settings['reason']);
    }

    $this->realEnv = $settings['env'];

    config([
        'database.connections.pgsql.host' => $this->realEnv['DB_HOST'],
        'database.connections.pgsql.port' => $this->realEnv['DB_PORT'],
        'database.connections.pgsql.database' => $this->realEnv['DB_DATABASE'],
        'database.connections.pgsql.username' => $this->realEnv['DB_USERNAME'],
        'database.connections.pgsql.password' => $this->realEnv['DB_PASSWORD'],
        'database.connections.pgsql.url' => null,
    ]);
    DB::purge('pgsql');

    try {
        DB::connection('pgsql')->getPdo();
    } catch (Throwable $e) {
        $this->markTestSkipped('The race test database is not reachable: '.$e->getMessage());
    }

    $this->previousDefault = config('database.default');
    DB::setDefaultConnection('pgsql');

    $this->sdao = RoleAssignment::query()->where('role', Role::SdaoMember->value)->orderBy('id')->get()->pluck('user_id')->take(2)->values();
    $this->template = WorkflowTemplate::query()->where('form_type', FormType::OrganizationRegistration->value)->whereNull('variant')->first();
    $this->sdaoStep = $this->template ? WorkflowStep::query()->where('workflow_template_id', $this->template->id)->where('role', Role::SdaoMember->value)->first() : null;
    $this->baseOrg = Organization::query()->first();

    if ($this->sdao->count() < 2 || ! $this->sdaoStep || $this->sdaoStep->required_approvals < 2 || ! $this->baseOrg) {
        DB::setDefaultConnection($this->previousDefault);
        $this->markTestSkipped('The race test database needs IdentitySeeder + WorkflowTemplateSeeder (2 SDAO members, a 2-approval registration step, an organization).');
    }

    sweepSeatRaceFixtures();
    $this->marker = sys_get_temp_dir().DIRECTORY_SEPARATOR.'seat-race-'.uniqid().'.marker';
});

afterEach(function () {
    if (isset($this->realEnv)) {
        sweepSeatRaceFixtures();
        DB::setDefaultConnection($this->previousDefault);
    }

    if (isset($this->marker) && file_exists($this->marker)) {
        unlink($this->marker);
    }
});

function raceUser(string $tag): User
{
    return User::factory()->create(['email' => "race-seat-{$tag}-".uniqid().'@example.test', 'account_status' => 'verified']);
}

/**
 * A real org with an adviser (bound to it) and a sitting president.
 *
 * @return array{org: Organization, adviser: User, president: User}
 */
function raceOrg(string $label, Organization $base): array
{
    $org = Organization::create(['name' => 'RACE-SEAT '.$label, 'school_id' => $base->school_id, 'program_id' => $base->program_id]);
    $adviser = raceUser('adviser');
    RoleAssignment::create(['user_id' => $adviser->id, 'role' => Role::Adviser, 'organization_id' => $org->id]);
    $president = raceUser('president');
    OrganizationMembership::create([
        'user_id' => $president->id, 'organization_id' => $org->id, 'position' => 'president',
        'academic_year' => '2026-2027', 'is_active' => true, 'started_at' => now(),
    ]);

    return ['org' => $org, 'adviser' => $adviser, 'president' => $president];
}

function activeSeatHolders(Organization $org, string $position): array
{
    return OrganizationMembership::where('organization_id', $org->id)->where('position', $position)->active()->pluck('user_id')->all();
}

test('two binds for the same seat at the same moment are serialized: the second waits, then succeeds, one active holder remains', function () {
    ['org' => $org, 'adviser' => $adviser, 'president' => $oldPresident] = raceOrg('bind-bind', $this->baseOrg);
    $s1 = raceUser('s1');
    $s2 = raceUser('s2');

    $bind = fn (User $student) => "\$a=\App\Models\User::findOrFail({$adviser->id}); \$o=\App\Models\Organization::findOrFail({$org->id}); \$s=\App\Models\User::findOrFail({$student->id}); app(\App\Organizations\BindOrganizationOfficer::class)->execute(\$a,\$o,\$s,\App\Enums\OfficerPosition::President);";

    $a = seatRaceProcess($this->realEnv, seatRaceScript($bind($s1), SEAT_RACE_HOLD_SECONDS, $this->marker));
    $a->start();
    waitForMarker($this->marker, $a);

    $startB = microtime(true);
    $b = seatRaceProcess($this->realEnv, seatRaceScript($bind($s2)));
    $b->run();
    $elapsedB = microtime(true) - $startB;
    $a->wait();

    expect(seatRaceResult($a))->toBe('OK');
    expect(seatRaceResult($b))->toBe('OK');
    // B was parked behind A's lock for (most of) the hold, not run alongside it.
    expect($elapsedB)->toBeGreaterThan(SEAT_RACE_HOLD_SECONDS * 0.5);

    // B ran strictly AFTER A: it closed A's fresh term and opened its own.
    expect(activeSeatHolders($org, 'president'))->toBe([$s2->id]);
    $rows = OrganizationMembership::where('organization_id', $org->id)->where('position', 'president')->orderBy('id')->get();
    expect($rows->pluck('user_id')->all())->toBe([$oldPresident->id, $s1->id, $s2->id]);
    expect($rows->pluck('is_active')->all())->toBe([false, false, true]);
    expect($rows->pluck('ended_at')->slice(0, 2)->every(fn ($t) => $t !== null))->toBeTrue();
});

test('an adviser bind racing an SDAO approval for the same seat is serialized, never a double-held seat', function () {
    ['org' => $org, 'adviser' => $adviser, 'president' => $president] = raceOrg('bind-approve', $this->baseOrg);
    $viaAdviser = raceUser('via-adviser');
    $viaSdao = raceUser('via-sdao');
    $request = OfficerChangeRequest::create([
        'organization_id' => $org->id, 'requested_by' => $president->id, 'position' => 'secretary',
        'nominee_id' => $viaSdao->id, 'status' => OfficerChangeRequestStatus::Pending,
    ]);

    $adviserBind = "\$a=\App\Models\User::findOrFail({$adviser->id}); \$o=\App\Models\Organization::findOrFail({$org->id}); \$s=\App\Models\User::findOrFail({$viaAdviser->id}); app(\App\Organizations\BindOrganizationOfficer::class)->execute(\$a,\$o,\$s,\App\Enums\OfficerPosition::Secretary);";
    $sdaoApprove = "\$u=\App\Models\User::findOrFail({$this->sdao[0]}); \$r=\App\Models\OfficerChangeRequest::findOrFail({$request->id}); app(\App\Organizations\Admin\ApproveOfficerChange::class)->execute(\$u,\$r);";

    $a = seatRaceProcess($this->realEnv, seatRaceScript($adviserBind, SEAT_RACE_HOLD_SECONDS, $this->marker));
    $a->start();
    waitForMarker($this->marker, $a);

    $startB = microtime(true);
    $b = seatRaceProcess($this->realEnv, seatRaceScript($sdaoApprove));
    $b->run();
    $elapsedB = microtime(true) - $startB;
    $a->wait();

    expect(seatRaceResult($a))->toBe('OK');
    expect(seatRaceResult($b))->toBe('OK');
    expect($elapsedB)->toBeGreaterThan(SEAT_RACE_HOLD_SECONDS * 0.5);

    // Last writer (the approval) wins the seat; the adviser's fresh holder was closed, not duplicated.
    expect(activeSeatHolders($org, 'secretary'))->toBe([$viaSdao->id]);
    expect(OrganizationMembership::where('organization_id', $org->id)->where('position', 'secretary')->count())->toBe(2);
    expect($request->fresh()->status)->toBe(OfficerChangeRequestStatus::Approved);
});

test('two registrations by the same student approved at the same moment cannot both make them President', function () {
    $founder = raceUser('founder');
    $docs = [];

    foreach (['X', 'Y'] as $label) {
        $org = Organization::create(['name' => 'RACE-SEAT founding-'.$label, 'school_id' => $this->baseOrg->school_id, 'program_id' => $this->baseOrg->program_id]);
        $adviser = raceUser('adviser-'.$label);
        RoleAssignment::create(['user_id' => $adviser->id, 'role' => Role::Adviser, 'organization_id' => null]);
        $doc = Document::create([
            'form_type' => FormType::OrganizationRegistration, 'status' => DocumentStatus::InReview,
            'current_step_position' => $this->sdaoStep->position, 'workflow_template_id' => $this->template->id,
            'organization_id' => $org->id, 'submitted_by' => $founder->id, 'title' => 'RACE-SEAT registration '.$label,
        ]);
        OrganizationRegistrationDetail::create(['document_id' => $doc->id, 'organization_type' => 'co_curricular', 'adviser_id' => $adviser->id, 'purpose_of_organization' => 'Race test.', 'contact_person' => 'Race Founder', 'contact_no' => '09171234567', 'email_address' => 'race@example.test', 'date_organized' => '2020-01-01']);
        // The FIRST SDAO member already approved — the second member's approval completes the quorum.
        DB::table('document_step_approvals')->insert(['document_id' => $doc->id, 'workflow_step_id' => $this->sdaoStep->id, 'step_position' => $this->sdaoStep->position, 'user_id' => $this->sdao[0], 'created_at' => now(), 'updated_at' => now()]);
        $docs[$label] = $doc;
    }

    $approve = fn (Document $d) => "\$u=\App\Models\User::findOrFail({$this->sdao[1]}); \$d=\App\Models\Document::findOrFail({$d->id}); app(\App\Registrations\ApproveOrganizationRegistration::class)->execute(\$d,\$u);";

    $a = seatRaceProcess($this->realEnv, seatRaceScript($approve($docs['X']), SEAT_RACE_HOLD_SECONDS, $this->marker));
    $a->start();
    waitForMarker($this->marker, $a);

    $b = seatRaceProcess($this->realEnv, seatRaceScript($approve($docs['Y'])));
    $b->run();
    $a->wait();

    expect(seatRaceResult($a))->toBe('OK');
    expect(seatRaceResult($b))->toStartWith('REFUSED:');
    expect(seatRaceResult($b))->toContain('founding student is now an active officer');

    // Exactly one seat for the founder; the loser's approval rolled back with it.
    expect(OrganizationMembership::where('user_id', $founder->id)->active()->count())->toBe(1);
    expect($docs['X']->fresh()->status)->toBe(DocumentStatus::Approved);
    expect($docs['Y']->fresh()->status)->toBe(DocumentStatus::InReview);
    expect(DB::table('document_step_approvals')->where('document_id', $docs['Y']->id)->count())->toBe(1);
});
