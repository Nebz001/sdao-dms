<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
});

test('full approval records Submitted + two Approved + Completed transitions', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $this->studentAlpha->id,
    ]);
    OrganizationRegistrationDetail::factory()->create(['document_id' => $doc->id]);

    $this->engine->submit($doc);
    $this->engine->approve($doc, $this->sdaoA);
    $this->engine->approve($doc, $this->sdaoB);
    $doc->refresh();

    $actions = $doc->transitions->pluck('action');
    expect($actions)->toContain(TransitionAction::Submitted)
        ->toContain(TransitionAction::Approved)
        ->toContain(TransitionAction::Completed);

    // Two individual Approved transitions (one per SDAO member) + one Completed.
    $approvedCount = $doc->transitions->where('action', TransitionAction::Approved)->count();
    $completedCount = $doc->transitions->where('action', TransitionAction::Completed)->count();
    expect($approvedCount)->toBe(2);
    expect($completedCount)->toBe(1);
});

test('return and resubmit records Returned and Resubmitted transitions', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $this->studentAlpha->id,
    ]);
    OrganizationRegistrationDetail::factory()->create(['document_id' => $doc->id]);

    $this->engine->submit($doc);
    $this->engine->returnForRevision($doc, $this->sdaoA, 'Please attach the constitution.');
    $doc->refresh();
    $this->engine->resubmit($doc);
    $doc->refresh();

    $actions = $doc->transitions->pluck('action');
    expect($actions)->toContain(TransitionAction::Returned)
        ->toContain(TransitionAction::Resubmitted);

    // Comment preserved on the Returned transition.
    $returnedTransition = $doc->transitions->firstWhere('action', TransitionAction::Returned);
    expect($returnedTransition->comment)->toBe('Please attach the constitution.');
});

test('show endpoint includes transition history with actor names', function () {
    $this->withoutVite();

    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $this->studentAlpha->id,
    ]);
    OrganizationRegistrationDetail::factory()->create(['document_id' => $doc->id]);

    $this->engine->submit($doc);
    $this->engine->approve($doc, $this->sdaoA);
    $this->engine->approve($doc, $this->sdaoB);
    $doc->refresh();

    $this->actingAs($this->studentAlpha);
    $response = $this->get(route('registrations.show', $doc));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('registrations/show')
        ->has('history', 4) // Submitted + Approved + Approved + Completed
    );
});
