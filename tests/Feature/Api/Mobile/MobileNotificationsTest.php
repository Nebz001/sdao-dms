<?php

use App\ActivityProposals\ProposalReference;
use App\Approval\ApprovalEngine;
use App\Approval\Contracts\ApproverNotifier;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Enums\TransitionAction;
use App\Http\Controllers\Api\Mobile\NotificationController;
use App\Models\ApprovalNotification;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ApproverHandOffNotification;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Fixtures\TestIdentitySeeder;

beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class]);
    $this->organization = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->chair = User::where('email', 'chair-cs@nu-lipa.edu.ph')->firstOrFail();
    $this->engine = app(ApprovalEngine::class);
});

function mobileNotificationHeaders(User $user): array
{
    return ['Authorization' => 'Bearer '.$user->createToken('Notification test')->plainTextToken];
}

function mobileNotificationProposal(Organization $organization): Document
{
    return Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'variant' => ProposalVariant::RegularOnCalendar,
        'organization_id' => $organization->id,
        'status' => DocumentStatus::Draft,
    ]);
}

test('mobile notification endpoints require Sanctum authentication', function () {
    $this->getJson('/api/mobile/notifications')->assertUnauthorized();
    $this->getJson('/api/mobile/notifications/unread-count')->assertUnauthorized();
    $this->patchJson('/api/mobile/notifications/read-all')->assertUnauthorized();
});

test('PostgreSQL casts the text notification payload before filtering its form type', function () {
    $user = new User;
    $user->id = $this->adviser->id;
    $user->setConnection('pgsql');

    $method = new ReflectionMethod(NotificationController::class, 'notificationsFor');
    $query = $method->invoke(app(NotificationController::class), $user);

    expect($query->toSql())
        ->toContain('cast("data" as jsonb) ->> \'form_type\' = ?');
});

test('an actionable Activity Proposal appears in the approver persistent inbox', function () {
    $document = mobileNotificationProposal($this->organization);
    $this->engine->submit($document, $this->adviser);

    $response = $this->withHeaders(mobileNotificationHeaders($this->adviser))
        ->getJson('/api/mobile/notifications');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'activity_proposal_ready_for_review')
        ->assertJsonPath('data.0.title', 'Proposal ready for review')
        ->assertJsonPath('data.0.body', 'An Activity Proposal is waiting in your review queue.')
        ->assertJsonPath('data.0.proposal_reference', ProposalReference::format($document))
        ->assertJsonPath('data.0.read_at', null)
        ->assertJsonPath('meta.unread_count', 1);

    Auth::forgetGuards();

    $this->withHeaders(mobileNotificationHeaders($this->chair))
        ->getJson('/api/mobile/notifications')
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.unread_count', 0);
});

test('an approver can read only their own mobile notification', function () {
    $document = mobileNotificationProposal($this->organization);
    $this->engine->submit($document, $this->adviser);
    $notification = $this->adviser->notifications()
        ->where('type', ApproverHandOffNotification::class)
        ->firstOrFail();

    $this->withHeaders(mobileNotificationHeaders($this->chair))
        ->patchJson("/api/mobile/notifications/{$notification->id}/read")
        ->assertNotFound();
    expect($notification->fresh()->read_at)->toBeNull();

    Auth::forgetGuards();

    $this->withHeaders(mobileNotificationHeaders($this->adviser))
        ->patchJson("/api/mobile/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJsonPath('data.id', $notification->id)
        ->assertJsonPath('data.read_at', fn ($value): bool => is_string($value));

    $this->withHeaders(mobileNotificationHeaders($this->adviser))
        ->getJson('/api/mobile/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.unread_count', 0);
});

test('mark all read affects only the authenticated approver', function () {
    $document = mobileNotificationProposal($this->organization);
    $this->engine->submit($document, $this->adviser);
    $this->engine->approve($document, $this->adviser);

    $adviserNotification = $this->adviser->notifications()->firstOrFail();
    $chairNotification = $this->chair->notifications()->firstOrFail();

    $this->withHeaders(mobileNotificationHeaders($this->adviser))
        ->patchJson('/api/mobile/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('data.unread_count', 0);

    expect($adviserNotification->fresh()->read_at)->not->toBeNull()
        ->and($chairNotification->fresh()->read_at)->toBeNull();
});

test('a repeated notifier call for the same workflow transition is idempotent', function () {
    $document = mobileNotificationProposal($this->organization);
    $this->engine->submit($document, $this->adviser);

    app(ApproverNotifier::class)->notify(
        $this->adviser,
        $document->fresh(),
        1,
        TransitionAction::Submitted,
    );

    expect(ApprovalNotification::query()
        ->where('document_id', $document->id)
        ->where('user_id', $this->adviser->id)
        ->count())->toBe(1)
        ->and($this->adviser->notifications()
            ->where('type', ApproverHandOffNotification::class)
            ->count())->toBe(1);
});

test('advancement and resubmission create one inbox notification per actionable occurrence', function () {
    $document = mobileNotificationProposal($this->organization);
    $this->engine->submit($document, $this->adviser);
    $this->engine->approve($document, $this->adviser);

    expect($this->chair->notifications()
        ->where('type', ApproverHandOffNotification::class)
        ->count())->toBe(1);

    $this->engine->returnForRevision($document->fresh(), $this->chair, 'Please revise.');
    $this->engine->resubmit($document->fresh(), $this->adviser);

    expect($this->chair->notifications()
        ->where('type', ApproverHandOffNotification::class)
        ->count())->toBe(2);
});
