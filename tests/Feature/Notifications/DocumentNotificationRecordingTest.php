<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Identity\Admin\RejectAccount;
use App\Identity\Admin\VerifyAccount;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Unlike tests/Feature/Mail/ApproverHandOffMailTest.php and
 * DocumentOutcomeMailTest.php (which fake the Notification facade and
 * inspect the notification objects in-memory), these tests let notifications
 * actually persist — the database channel is deliberately NOT queued (see
 * ApproverHandOffNotification::viaConnections(), 'database' => 'sync') so
 * every assertion here reads real rows out of the `notifications` table,
 * exactly what the bell itself reads via HandleInertiaRequests::share().
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
    $this->student = User::factory()->create();
});

test('a hand-off writes a database notification pointing at the review route', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $this->student->id,
    ]);

    $this->engine->submit($doc, $this->student);

    $notification = $this->sdaoA->notifications()->first();

    // Origin-relative, not absolute — a notification row can be written from
    // a CLI/queue-worker context with a different APP_URL than whatever
    // origin the browser is actually on, and an absolute URL baked at write
    // time becomes a cross-origin URL the moment those differ. Inertia's
    // router.visit() issues an XHR with no same-origin fallback, so a
    // cross-origin bell link silently fails to navigate. See
    // App\Support\DocumentUrls' class docblock. Mail keeps the absolute
    // variant — see ApproverHandOffMailTest.
    expect($notification)->not->toBeNull()
        ->and($notification->data['kind'])->toBe('approver_hand_off')
        ->and($notification->data['document_id'])->toBe($doc->id)
        ->and($notification->data['url'])->toBe(route('review.registrations.show', $doc, absolute: false))
        ->and($notification->read_at)->toBeNull();
});

test('a dual-SDAO hand-off writes a database notification for both members', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);

    $this->engine->submit($doc, $this->sdaoA);

    expect($this->sdaoA->notifications()->count())->toBe(1)
        ->and($this->sdaoB->notifications()->count())->toBe(1);
});

test('a partial SDAO approval writes no further hand-off notification', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $this->engine->submit($doc, $this->sdaoA);
    $before = User::query()->get()->sum(fn (User $u) => $u->notifications()->count());

    // First of two required SDAO approvals — quorum not yet met, nobody new is notified.
    $this->engine->approve($doc, $this->sdaoA);

    $after = User::query()->get()->sum(fn (User $u) => $u->notifications()->count());
    expect($after)->toBe($before);
});

test('a final-approval outcome writes a database notification pointing at the submitter-facing route', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $this->student->id,
    ]);
    $this->engine->submit($doc, $this->student);

    $this->engine->approve($doc, $this->sdaoA);
    $this->engine->approve($doc, $this->sdaoB);

    $notification = $this->student->notifications()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['kind'])->toBe('document_outcome')
        ->and($notification->data['status'])->toBe('approved')
        ->and($notification->data['url'])->toBe(route('registrations.show', $doc, absolute: false));
});

test('verifying an account writes a database notification', function () {
    $account = User::factory()->unverifiedAccount()->create();

    app(VerifyAccount::class)->execute($this->sdaoA, $account);

    $notification = $account->notifications()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['kind'])->toBe('account_verified')
        ->and($notification->data['url'])->toBe(route('dashboard', absolute: false));
});

test('rejecting an account writes a database notification', function () {
    $account = User::factory()->unverifiedAccount()->create();

    app(RejectAccount::class)->execute($this->sdaoA, $account);

    $notification = $account->notifications()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['kind'])->toBe('account_rejected')
        ->and($notification->data['url'])->toBe(route('dashboard', absolute: false));
});
