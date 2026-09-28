<?php

use App\ActivityProposals\ProposalReference;
use App\Approval\ApprovalEngine;
use App\Approval\Contracts\ApproverNotifier;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Enums\TransitionAction;
use App\Jobs\CheckExpoPushReceipt;
use App\Jobs\SendExpoPushNotification;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\PushToken;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class]);
    $this->organization = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
});

function expoPushTestDocument(Organization $organization, FormType $formType = FormType::ActivityProposal): Document
{
    return Document::factory()->create([
        'form_type' => $formType,
        'variant' => $formType === FormType::ActivityProposal ? ProposalVariant::RegularOnCalendar : null,
        'organization_id' => $organization->id,
        'status' => DocumentStatus::Draft,
    ]);
}

function expoPushTestRegistration(User $user, string $token): PushToken
{
    return $user->pushTokens()->create([
        'device_id' => (string) Str::uuid(),
        'token' => $token,
        'platform' => 'android',
        'device_name' => 'Test device',
        'last_seen_at' => now(),
    ]);
}

test('only Activity Proposal hand-offs enter the mobile push queue', function () {
    Notification::fake();
    Bus::fake();
    expoPushTestRegistration($this->adviser, 'ExponentPushToken[queue-trigger-device]');
    $proposal = expoPushTestDocument($this->organization);
    $calendarDocument = expoPushTestDocument($this->organization, FormType::ActivityCalendar);
    DB::partialMock()
        ->shouldReceive('afterCommit')
        ->once()
        ->andReturnUsing(fn (callable $callback) => $callback());

    $notifier = app(ApproverNotifier::class);
    $notifier->notify($this->adviser, $proposal, 1, TransitionAction::Submitted);
    $notifier->notify($this->adviser, $calendarDocument, 1, TransitionAction::Submitted);

    Bus::assertDispatchedTimes(SendExpoPushNotification::class, 1);
    Bus::assertDispatched(SendExpoPushNotification::class, fn (SendExpoPushNotification $job): bool => $job->userId === $this->adviser->id
            && $job->documentId === $proposal->id
            && $job->stepPosition === 1
            && $job->triggerAction === TransitionAction::Submitted
            && $job->connection === 'database'
    );
});

test('registered devices do not change the approval stage or transition history', function () {
    Notification::fake();
    $chair = User::where('email', 'chair-cs@nu-lipa.edu.ph')->firstOrFail();
    expoPushTestRegistration($this->adviser, 'ExponentPushToken[web-flow-adviser]');
    expoPushTestRegistration($chair, 'ExponentPushToken[web-flow-chair]');
    $document = expoPushTestDocument($this->organization);
    $engine = app(ApprovalEngine::class);

    $engine->submit($document, $this->adviser);
    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::InReview)
        ->and($document->current_step_position)->toBe(1);

    $engine->approve($document, $this->adviser);
    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::InReview)
        ->and($document->current_step_position)->toBe(2)
        ->and(DocumentTransition::where('document_id', $document->id)
            ->where('action', TransitionAction::Advanced->value)
            ->exists())->toBeTrue();
});

test('a failed Expo request cannot roll back an already-actionable workflow stage', function () {
    Notification::fake();
    $chair = User::where('email', 'chair-cs@nu-lipa.edu.ph')->firstOrFail();
    $document = expoPushTestDocument($this->organization);
    $engine = app(ApprovalEngine::class);
    $engine->submit($document, $this->adviser);
    $engine->approve($document, $this->adviser);
    expoPushTestRegistration($chair, 'ExponentPushToken[failed-delivery-chair]');
    Http::fake([
        config('services.expo.push_url') => Http::response([], 503),
    ]);

    expect(fn () => (new SendExpoPushNotification(
        $chair->id,
        $document->id,
        2,
        TransitionAction::Advanced,
    ))->handle())->toThrow(RuntimeException::class);

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::InReview)
        ->and($document->current_step_position)->toBe(2);
});

test('Expo payload contains generic lock-screen text and the proposal tap reference', function () {
    Notification::fake();
    Bus::fake();
    $document = expoPushTestDocument($this->organization);
    app(ApprovalEngine::class)->submit($document, $this->adviser);
    $token = 'ExponentPushToken[delivery-device]';
    $registration = expoPushTestRegistration($this->adviser, $token);
    Http::fake([
        config('services.expo.push_url') => Http::response([
            'data' => ['status' => 'ok', 'id' => 'expo-ticket-123'],
        ]),
    ]);

    (new SendExpoPushNotification(
        $this->adviser->id,
        $document->id,
        1,
        TransitionAction::Submitted,
    ))->handle();

    Http::assertSent(fn (Request $request): bool => $request->url() === config('services.expo.push_url')
            && $request['to'] === $token
            && $request['title'] === 'Activity Proposal needs your review'
            && $request['body'] === 'A proposal is ready for your review.'
            && $request['data']['proposalReference'] === ProposalReference::format($document)
    );
    Bus::assertDispatched(CheckExpoPushReceipt::class, fn (CheckExpoPushReceipt $job): bool => $job->pushTokenId === $registration->id
            && $job->ticketId === 'expo-ticket-123'
            && $job->tokenHash === hash('sha256', $token)
    );
});

test('a notification queued for an old stage does not reach an approver after advancement', function () {
    Notification::fake();
    $document = expoPushTestDocument($this->organization);
    $engine = app(ApprovalEngine::class);
    $engine->submit($document, $this->adviser);
    $engine->approve($document, $this->adviser);
    expoPushTestRegistration($this->adviser, 'ExponentPushToken[stale-step-device]');
    Http::fake();

    (new SendExpoPushNotification(
        $this->adviser->id,
        $document->id,
        1,
        TransitionAction::Submitted,
    ))->handle();

    Http::assertNothingSent();
});

test('DeviceNotRegistered ticket and receipt errors remove only the affected token', function () {
    Notification::fake();
    $document = expoPushTestDocument($this->organization);
    app(ApprovalEngine::class)->submit($document, $this->adviser);
    $ticketToken = 'ExponentPushToken[invalid-ticket-device]';
    $ticketRegistration = expoPushTestRegistration($this->adviser, $ticketToken);
    Http::fake([
        config('services.expo.push_url') => Http::response([
            'data' => ['status' => 'error', 'details' => ['error' => 'DeviceNotRegistered']],
        ]),
    ]);

    (new SendExpoPushNotification(
        $this->adviser->id,
        $document->id,
        1,
        TransitionAction::Submitted,
    ))->handle();

    $this->assertDatabaseMissing('push_tokens', ['id' => $ticketRegistration->id]);

    $receiptToken = 'ExponentPushToken[invalid-receipt-device]';
    $receiptRegistration = expoPushTestRegistration($this->adviser, $receiptToken);
    Http::fake([
        config('services.expo.receipts_url') => Http::response([
            'data' => ['expo-ticket-456' => [
                'status' => 'error',
                'details' => ['error' => 'DeviceNotRegistered'],
            ]],
        ]),
    ]);

    (new CheckExpoPushReceipt(
        $receiptRegistration->id,
        'expo-ticket-456',
        hash('sha256', $receiptToken),
    ))->handle();

    $this->assertDatabaseMissing('push_tokens', ['id' => $receiptRegistration->id]);
});
