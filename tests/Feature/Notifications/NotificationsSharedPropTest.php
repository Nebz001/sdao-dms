<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\WorkflowTemplateSeeder;

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
        );
});
