<?php

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\WorkflowStep;
use App\Models\WorkflowTemplate;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Regression test for the quorum race fixed by the lockForUpdate() call at
 * the top of ApprovalEngine::approve()'s transaction: two SDAO members
 * approving a dual-approval step within moments of each other could each
 * read the quorum count before the other's insert committed, both conclude
 * "not enough approvals yet", and the document is left stuck at In Review
 * forever even though both approvals were recorded (proven live against
 * this project's dev Postgres database while investigating the bug).
 *
 * This can only be demonstrated against a real MVCC database with row-level
 * locking. The default suite runs on in-memory SQLite (phpunit.xml) for
 * speed, where lockForUpdate() is a no-op and a second PHP process couldn't
 * even see the first's data (a fresh :memory: database per process). So
 * this test talks directly to the real development Postgres database
 * (read straight from .env, bypassing phpunit.xml's sqlite override) and
 * drives two REAL overlapping approve() calls in separate OS processes —
 * the only way to genuinely reproduce cross-connection contention. It
 * skips itself if that database isn't reachable or isn't seeded with an
 * Organization Registration workflow + 2 SDAO members (the standard
 * db:seed + WorkflowTemplateSeeder/RealRosterSeeder outcome).
 *
 * Deliberately does not use RefreshDatabase: this test's assertions run
 * against the 'pgsql' connection explicitly, never the suite's default
 * (sqlite) connection, so RefreshDatabase's transaction wrapping around
 * the default connection neither helps nor interferes here. Fixtures this
 * test creates on 'pgsql' are cleaned up manually in afterEach() instead.
 */
function realDevDatabaseEnv(): array
{
    $values = Dotenv\Dotenv::createArrayBacked(base_path())->load();

    return [
        'DB_CONNECTION' => 'pgsql',
        'DB_HOST' => $values['DB_HOST'] ?? '127.0.0.1',
        'DB_PORT' => $values['DB_PORT'] ?? '5432',
        'DB_DATABASE' => $values['DB_DATABASE'] ?? null,
        'DB_USERNAME' => $values['DB_USERNAME'] ?? 'postgres',
        'DB_PASSWORD' => $values['DB_PASSWORD'] ?? '',
    ];
}

/** Builds the child process's inline script: approve as $userId, optionally holding the transaction open afterward. */
function approveScript(int $documentId, int $userId, int $holdSeconds): string
{
    return <<<PHP
        \$doc = \App\Models\Document::findOrFail({$documentId});
        \$user = \App\Models\User::findOrFail({$userId});
        \Illuminate\Support\Facades\DB::transaction(function () use (\$doc, \$user) {
            app(\App\Approval\ApprovalEngine::class)->approve(\$doc, \$user);
            if ({$holdSeconds} > 0) {
                sleep({$holdSeconds});
            }
        });
        echo 'STATUS:'.\$doc->fresh()->status->value;
        PHP;
}

beforeEach(function () {
    $this->realEnv = realDevDatabaseEnv();

    if (! $this->realEnv['DB_DATABASE']) {
        $this->markTestSkipped('No DB_DATABASE configured in .env; skipping live-Postgres concurrency test.');
    }

    config([
        'database.connections.pgsql.host' => $this->realEnv['DB_HOST'],
        'database.connections.pgsql.port' => $this->realEnv['DB_PORT'],
        'database.connections.pgsql.database' => $this->realEnv['DB_DATABASE'],
        'database.connections.pgsql.username' => $this->realEnv['DB_USERNAME'],
        'database.connections.pgsql.password' => $this->realEnv['DB_PASSWORD'],
    ]);
    DB::purge('pgsql');

    try {
        DB::connection('pgsql')->getPdo();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Real dev Postgres database is not reachable: '.$e->getMessage());
    }

    $template = WorkflowTemplate::on('pgsql')
        ->where('form_type', FormType::OrganizationRegistration->value)
        ->whereNull('variant')
        ->first();

    $step = $template
        ? WorkflowStep::on('pgsql')
            ->where('workflow_template_id', $template->id)
            ->where('role', Role::SdaoMember->value)
            ->first()
        : null;

    $sdaoUserIds = $step
        ? RoleAssignment::on('pgsql')->where('role', Role::SdaoMember->value)->pluck('user_id')->take(2)->values()
        : collect();

    $organization = Organization::on('pgsql')->first();

    if (! $template || ! $step || $step->required_approvals < 2 || $sdaoUserIds->count() < 2 || ! $organization) {
        $this->markTestSkipped('Dev Postgres database is not seeded with an Organization Registration workflow + 2 SDAO members (run db:seed first).');
    }

    $this->step = $step;
    $this->template = $template;
    $this->userAId = $sdaoUserIds[0];
    $this->userBId = $sdaoUserIds[1];
    $this->organizationId = $organization->id;
});

afterEach(function () {
    if (isset($this->documentId)) {
        DB::connection('pgsql')->table('document_transitions')->where('document_id', $this->documentId)->delete();
        DB::connection('pgsql')->table('document_step_approvals')->where('document_id', $this->documentId)->delete();
        DB::connection('pgsql')->table('documents')->where('id', $this->documentId)->delete();
    }
});

test('two SDAO members approving within moments of each other both still land the document on Approved', function () {
    $document = Document::on('pgsql')->create([
        'form_type' => FormType::OrganizationRegistration,
        'status' => DocumentStatus::InReview,
        'current_step_position' => $this->step->position,
        'workflow_template_id' => $this->template->id,
        'organization_id' => $this->organizationId,
        'title' => 'RACE-REGRESSION-TEST (auto-created and deleted by ConcurrentApprovalRaceTest)',
    ]);
    $this->documentId = $document->id;

    $holdSeconds = 3;
    $childEnv = $this->realEnv;

    // Approver A: approves, then holds its transaction (and the row lock
    // ApprovalEngine::approve() now takes) open for a few seconds — standing
    // in for the natural, much shorter window a real request holds it open
    // for, just stretched out so a second process reliably lands inside it.
    $processA = new Process(
        ['php', 'artisan', 'tinker', '--execute='.approveScript($document->id, $this->userAId, $holdSeconds)],
        base_path(),
        $childEnv,
    );
    $processA->start();

    // Give A a moment to reach and acquire its lock before B starts, so B is
    // guaranteed to arrive while A's transaction is still open.
    usleep(750_000);

    // Approver B: starts while A's transaction is still open. Pre-fix, this
    // would run straight through, count only its own approval (A's insert
    // isn't committed yet), conclude quorum isn't met, and return — leaving
    // the document stuck at In Review even after both approvals exist.
    $processB = new Process(
        ['php', 'artisan', 'tinker', '--execute='.approveScript($document->id, $this->userBId, 0)],
        base_path(),
        $childEnv,
    );
    $startB = microtime(true);
    $processB->start();
    $processB->wait();
    $elapsedB = microtime(true) - $startB;

    $processA->wait();

    expect($processA->isSuccessful())->toBeTrue($processA->getErrorOutput());
    expect($processB->isSuccessful())->toBeTrue($processB->getErrorOutput());

    // Corroborates that B was actually blocked on A's row lock rather than
    // the fix coincidentally producing the right count — without the lock,
    // B would return almost immediately instead of waiting out A's hold.
    expect($elapsedB)->toBeGreaterThan($holdSeconds * 0.5);

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::Approved);
    expect($document->current_step_position)->toBeNull();

    $approvalCount = DB::connection('pgsql')->table('document_step_approvals')
        ->where('document_id', $document->id)
        ->count();
    expect($approvalCount)->toBe(2);

    $completed = DB::connection('pgsql')->table('document_transitions')
        ->where('document_id', $document->id)
        ->where('action', 'completed')
        ->exists();
    expect($completed)->toBeTrue();
});
