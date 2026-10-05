<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use App\Support\NavCounts;
use Database\Seeders\WorkflowTemplateSeeder;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
});

test('an SDAO member gets real counts for the pending-accounts and officer-change badges', function () {
    User::factory()->count(3)->unverifiedAccount()->create();

    $counts = app(NavCounts::class)->for($this->sdaoA);

    expect($counts['accounts']['pending'])->toBe(
        User::query()->where('account_status', 'unverified')->count(),
    );
    expect($counts['accounts']['pending'])->toBeGreaterThanOrEqual(3);
    expect($counts['accounts']['officerChanges'])->toBe(0);
});

test('a student gets zero staff counts and counts only their own organization documents', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $before = app(NavCounts::class)->for($this->studentAlpha);

    expect($before['stuck'])->toBe(0)
        ->and($before['accounts'])->toBe(['pending' => 0, 'officerChanges' => 0])
        ->and($before['review'])->each->toBe(0);

    $document = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $this->studentAlpha->id,
    ]);
    OrganizationRegistrationDetail::factory()->create(['document_id' => $document->id, 'organization_type' => OrganizationType::CoCurricular]);
    app(ApprovalEngine::class)->submit($document, $this->studentAlpha);

    $after = app(NavCounts::class)->for($this->studentAlpha);

    expect($after['documents']['registrations'])->toBe($before['documents']['registrations'] + 1)
        ->and($after['documents']['history'])->toBe($before['documents']['history'] + 1);
});

test('the counts are shared with the page lazily and match the service', function () {
    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertInertia(fn ($page) => $page
            ->where('navCounts.accounts.pending', app(NavCounts::class)->for($this->sdaoA)['accounts']['pending'])
            ->has('navCounts.review.registrations')
        );
});
