<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Real-click regression test for the notification bell. Two prior
 * investigation passes wrongly concluded the bell worked: one checked that
 * each notification's stored `url` resolved to a real, authorized route via
 * direct HTTP requests, the other mocked router.visit() in a frontend unit
 * test. Neither can catch this bug — it only exists in the browser. The
 * actual failure (confirmed live): the stored URL was absolute
 * (App\Support\DocumentUrls used route()'s default, which bakes in APP_URL),
 * and Inertia's router.visit() issues a plain XHR with no same-origin
 * fallback, so a notification link whose origin differs even slightly from
 * wherever the user is actually browsing (e.g. stored `localhost:8000`,
 * browsed from `127.0.0.1:8000`) is silently CORS-blocked. Two more real
 * bugs compounded it: the dropdown never closed (the row is a plain
 * <button>, not a DropdownMenuItem, so Radix's auto-close never fired, and
 * its default modal={true} left `pointer-events: none` on <body>), and
 * mark-read's router.patch() competed with router.visit() for Inertia's
 * single in-flight visit slot.
 *
 * This test drives an actual rendered click via Playwright (pestphp/
 * pest-plugin-browser), the same way the user does, against both a relative
 * URL (the current, correct default) and a simulated legacy absolute,
 * cross-origin URL (Pest's browser server always binds 127.0.0.1 — see
 * LaravelHttpServer::DEFAULT_HOST — so a stored `localhost` URL is
 * guaranteed to be a different origin, reproducing the exact failure mode).
 */
test('clicking a notification row navigates to the correct document and closes the dropdown, for two different notification kinds', function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class]);

    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $engine = app(ApprovalEngine::class);

    // Case 1: a real approver_hand_off notification, generated the normal
    // way (App\Support\DocumentUrls::pathForReviewer() — relative by
    // default now).
    $doc1 = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $student->id,
    ]);
    $engine->submit($doc1, $student);

    // Case 2: same real flow, but this notification's stored url is then
    // rewritten to an absolute, different-origin URL — simulating a row
    // written before the fix, or from a CLI/queue-worker context whose
    // APP_URL differs from the browsing origin.
    $doc2 = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $student->id,
    ]);
    $engine->submit($doc2, $student);

    $notification2 = $sdaoA->notifications()
        ->whereJsonContains('data->document_id', $doc2->id)
        ->firstOrFail();
    $data2 = $notification2->data;
    $data2['url'] = 'http://localhost'.$data2['url'];
    DB::table('notifications')->where('id', $notification2->id)->update(['data' => json_encode($data2)]);

    $page = visit('/login')
        ->fill('Email address', $sdaoA->email)
        ->fill('Password', 'password')
        ->click('Log in')
        ->assertPathIs('/admin/dashboard');

    $raw = $page->page();

    // --- Case 2 first (legacy absolute, cross-origin url) — most recent, so it's the top row ---
    $raw->locator('button[aria-label^="Notifications"]')->first()->click();
    $raw->waitForSelector('[data-slot="dropdown-menu-content"]');

    $rows = $raw->locator('[data-slot="dropdown-menu-content"] ul li button:not([aria-label="Mark as read"])');
    expect($rows->count())->toBe(2);

    // Both notifications can land in the same second, so latest() ordering
    // between them isn't guaranteed — match by title rather than position.
    $doc2Row = $rows->filter(['hasText' => $doc2->title]);
    expect($doc2Row->count())->toBe(1);

    $doc2Row->first()->click();
    $raw->waitForURL('**/review/registrations/'.$doc2->id);

    $page->assertPathIs('/review/registrations/'.$doc2->id)
        ->assertMissing('[data-slot="dropdown-menu-content"]')
        ->assertSee($doc2->title);

    // Body must be interactive again — Radix's modal DropdownMenu leaves
    // `pointer-events: none` on <body> until it actually closes.
    expect($raw->evaluate('getComputedStyle(document.body).pointerEvents'))->toBe('auto');

    // --- Case 1 (relative url, the normal path) — go back to the dashboard and click it ---
    $raw->goto((string) route('admin.dashboard.index'));

    $raw->locator('button[aria-label^="Notifications"]')->first()->click();
    $raw->waitForSelector('[data-slot="dropdown-menu-content"]');

    $rows = $raw->locator('[data-slot="dropdown-menu-content"] ul li button:not([aria-label="Mark as read"])');
    $doc1Row = $rows->filter(['hasText' => $doc1->title]);
    expect($doc1Row->count())->toBe(1);

    $doc1Row->first()->click();
    $raw->waitForURL('**/review/registrations/'.$doc1->id);

    $page->assertPathIs('/review/registrations/'.$doc1->id)
        ->assertMissing('[data-slot="dropdown-menu-content"]')
        ->assertSee($doc1->title);

    expect($raw->evaluate('getComputedStyle(document.body).pointerEvents'))->toBe('auto');
});
