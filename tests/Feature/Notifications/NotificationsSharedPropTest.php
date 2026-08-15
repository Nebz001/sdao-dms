<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ApproverHandOffNotification;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * HandleInertiaRequests::share() exposes `notifications` as a closure prop —
 * these tests confirm it's evaluated (and correct) on a normal full-page
 * visit, and is null rather than erroring for a guest.
 */
test('a guest gets a null notifications prop', function () {
    $this->get(route('login'))
        ->assertInertia(fn ($page) => $page->where('notifications', null));
});

test('an authenticated user with no notifications sees a zero unread count and empty items', function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class]);
    $sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();

    // An SDAO member hitting /dashboard redirects to admin.dashboard.index
    // (DashboardController) — hit that route directly for an Inertia 200.
    $this->actingAs($sdaoA)
        ->get(route('admin.dashboard.index'))
        ->assertInertia(fn ($page) => $page
            ->where('notifications.unreadCount', 0)
            ->where('notifications.items', [])
        );
});

test('a legacy notification row with an absolute url is exposed to the client as a relative path', function () {
    // Simulates a row written before the origin-relative fix (or from a CLI/
    // queue-worker context whose APP_URL differs from the browser's actual
    // origin) — DocumentUrls::pathForReviewer()/pathForSubmitter() and the
    // account/join-request notifications now store relative paths going
    // forward, but existing rows keep whatever they were written with.
    // HandleInertiaRequests::toRelativePath() must normalize this at read
    // time regardless of what's actually stored, since an absolute URL
    // baked at write time becomes cross-origin — and therefore silently
    // unclickable via router.visit() — the moment APP_URL and the browsing
    // origin diverge.
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class]);
    $sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();

    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => ApproverHandOffNotification::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $sdaoA->id,
        'data' => json_encode([
            'kind' => 'approver_hand_off',
            'title' => 'Legacy row',
            'body' => 'Written with an absolute URL',
            'url' => 'http://localhost:8000/review/registrations/999',
            'document_id' => 999,
            'form_type' => 'organization_registration',
            'organization' => 'Legacy Org',
            'status' => null,
        ]),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($sdaoA)
        ->get(route('admin.dashboard.index'))
        ->assertInertia(fn ($page) => $page
            ->where('notifications.items.0.url', '/review/registrations/999')
        );
});

test('the shared prop reflects a real hand-off notification', function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class]);
    $sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
    $org = Organization::where('name', 'Computing Society')->firstOrFail();

    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Draft,
    ]);
    app(ApprovalEngine::class)->submit($doc, $sdaoB);

    $this->actingAs($sdaoA)
        ->get(route('admin.dashboard.index'))
        ->assertInertia(fn ($page) => $page
            ->where('notifications.unreadCount', 1)
            ->has('notifications.items', 1)
            ->where('notifications.items.0.kind', 'approver_hand_off')
            ->where('notifications.items.0.readAt', null)
            // Only document_outcome ever populates this — see the next test.
            ->where('notifications.items.0.status', null)
        );
});

test('the shared prop forwards a document outcome notification\'s status, for the bell\'s per-outcome icon', function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class]);
    $sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::factory()->create();

    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $student->id,
    ]);
    app(ApprovalEngine::class)->submit($doc, $student);
    app(ApprovalEngine::class)->approve($doc, $sdaoA);
    app(ApprovalEngine::class)->approve($doc, $sdaoB);

    $this->actingAs($student)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('notifications.items.0.kind', 'document_outcome')
            ->where('notifications.items.0.status', 'approved')
        );
});
