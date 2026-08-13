<?php

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * The archive index sits behind the same `can:access-admin` gate as the rest
 * of admin/* (AppServiceProvider::configureGates(), Role::SdaoMember only).
 * These tests pin that boundary for the new route, mirroring
 * ReviewQueueAuthorizationTest's cast of non-SDAO roles.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->adviserOne = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->deanCcit = User::where('email', 'dean-ccit@nu-lipa.edu.ph')->firstOrFail();
});

test('an SDAO member can open the document archive', function () {
    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index'))
        ->assertOk();
});

test('a student officer, an adviser, a dean, and a bare account all get 403 on the document archive', function () {
    $this->actingAs($this->studentAlpha)->withoutVite()->get(route('admin.archive.index'))->assertForbidden();
    $this->actingAs($this->adviserOne)->withoutVite()->get(route('admin.archive.index'))->assertForbidden();
    $this->actingAs($this->deanCcit)->withoutVite()->get(route('admin.archive.index'))->assertForbidden();

    $bareUser = User::factory()->create();
    $this->actingAs($bareUser)->withoutVite()->get(route('admin.archive.index'))->assertForbidden();
});

test('SDAO global scope is preserved: the archive shows terminal documents from every organization', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $itGuild = Organization::where('name', 'IT Guild')->firstOrFail();

    Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Approved,
    ]);
    Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $itGuild->id,
        'status' => DocumentStatus::Approved,
    ]);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('documents.data', 2));
});
