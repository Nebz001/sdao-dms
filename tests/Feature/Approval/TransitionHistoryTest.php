<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
});

// Test 29: every action writes a transition row with actor, action, from/to status, step
test('every engine action writes a transition row', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);

    // submit
    $this->engine->submit($doc, $this->sdaoA);
    $doc->refresh();
    $t1 = $doc->transitions()->orderBy('id')->first();
    expect($t1->action)->toBe(TransitionAction::Submitted);
    expect($t1->from_status)->toBe(DocumentStatus::Draft);
    expect($t1->to_status)->toBe(DocumentStatus::InReview);
    expect($t1->actor_id)->toBe($this->sdaoA->id); // invariant #7: actor recorded on every transition

    // first SDAO approval (partial — no advance)
    $this->engine->approve($doc, $this->sdaoA);
    $t2 = $doc->transitions()->orderBy('id')->get()->last();
    expect($t2->action)->toBe(TransitionAction::Approved);
    expect($t2->actor_id)->toBe($this->sdaoA->id);
    expect($t2->step_position)->toBe(1);

    // return for revision
    $this->engine->returnForRevision($doc, $this->sdaoA, 'Please fix.');
    $doc->refresh();
    $t3 = $doc->transitions()->orderBy('id')->get()->last();
    expect($t3->action)->toBe(TransitionAction::Returned);
    expect($t3->comment)->toBe('Please fix.');
    expect($t3->from_status)->toBe(DocumentStatus::InReview);
    expect($t3->to_status)->toBe(DocumentStatus::Returned);

    // resubmit
    $this->engine->resubmit($doc, $this->sdaoA);
    $doc->refresh();
    $t4 = $doc->transitions()->orderBy('id')->get()->last();
    expect($t4->action)->toBe(TransitionAction::Resubmitted);
    expect($t4->from_status)->toBe(DocumentStatus::Returned);
    expect($t4->to_status)->toBe(DocumentStatus::InReview);
    expect($t4->actor_id)->toBe($this->sdaoA->id); // invariant #7: actor recorded on resubmit
});

test('completed document transition is recorded when final step is approved', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $this->engine->submit($doc, $this->sdaoA);
    $this->engine->approve($doc, $this->sdaoA);
    $this->engine->approve($doc, $this->sdaoB);
    $doc->refresh();

    $completed = $doc->transitions()->where('action', TransitionAction::Completed)->first();
    expect($completed)->not->toBeNull();
    expect($completed->to_status)->toBe(DocumentStatus::Approved);
    expect($completed->actor_id)->toBe($this->sdaoB->id);
});

// Test 30: history is append-only and ordered; completed doc shows full path
test('a completed short-chain document shows submitted, approved, approved, completed in order', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $this->engine->submit($doc, $this->sdaoA);
    $this->engine->approve($doc, $this->sdaoA);
    $this->engine->approve($doc, $this->sdaoB);
    $doc->refresh();

    $actions = $doc->transitions()->orderBy('id')->pluck('action');
    expect($actions->toArray())->toBe([
        TransitionAction::Submitted,
        TransitionAction::Approved,  // sdaoA individual approval
        TransitionAction::Approved,  // sdaoB individual approval
        TransitionAction::Completed, // quorum reached — final step done
    ]);
});

test('rejected document transition is recorded with actor and comment', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $this->engine->submit($doc, $this->sdaoA);
    $this->engine->reject($doc, $this->sdaoA, 'Non-compliant.');
    $doc->refresh();

    $rejection = $doc->transitions()->where('action', TransitionAction::Rejected)->first();
    expect($rejection)->not->toBeNull();
    expect($rejection->actor_id)->toBe($this->sdaoA->id);
    expect($rejection->comment)->toBe('Non-compliant.');
    expect($rejection->to_status)->toBe(DocumentStatus::Rejected);
});
