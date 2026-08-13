<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ApproverHandOffNotification;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Invariant #9's actual delivery channel is now ApproverHandOffNotification
 * (mail + database off one class — see its docblock), fired from
 * RecordingApproverNotifier via Notification::send(), still triggered once
 * per approver from ApprovalEngine::activateStep (trigger itself unchanged).
 * Notification::fake() intercepts before via()/toMail()/toDatabase() ever
 * run, so mail content assertions below render the Mailable explicitly via
 * $notification->toMail($notifiable) rather than relying on a captured
 * Mailable instance the way Mail::fake() used to hand one over.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->chair = User::where('email', 'chair-cs@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();

    Notification::fake();
});

test('submitting sends a real email to the step-1 approver', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'variant' => ProposalVariant::RegularOnCalendar,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);

    $this->engine->submit($doc, $this->adviser);

    Notification::assertSentTo(
        $this->adviser,
        ApproverHandOffNotification::class,
        fn (ApproverHandOffNotification $notification, array $channels) => in_array('mail', $channels, true)
            && $notification->document->id === $doc->id
            && $notification->stepPosition === 1,
    );
});

test('submitting a short-chain document emails both SDAO members', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);

    $this->engine->submit($doc, $this->sdaoA);

    Notification::assertSentTo($this->sdaoA, ApproverHandOffNotification::class);
    Notification::assertSentTo($this->sdaoB, ApproverHandOffNotification::class);
    Notification::assertSentTimes(ApproverHandOffNotification::class, 2);
});

test('advancing to step 2 emails the chair', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'variant' => ProposalVariant::RegularOnCalendar,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $this->engine->submit($doc, $this->adviser);

    $this->engine->approve($doc, $this->adviser);

    Notification::assertSentTo(
        $this->chair,
        ApproverHandOffNotification::class,
        fn (ApproverHandOffNotification $notification) => $notification->stepPosition === 2,
    );
});

test('resubmit emails the resuming step approver again', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'variant' => ProposalVariant::RegularOnCalendar,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $this->engine->submit($doc, $this->adviser);
    $this->engine->returnForRevision($doc, $this->adviser, 'Missing signature.');
    $doc->refresh();

    $this->engine->resubmit($doc, $this->adviser);

    // Once on submit, once on resubmit — both to the adviser.
    Notification::assertSentTimes(ApproverHandOffNotification::class, 2);
    Notification::assertSentTo($this->adviser, ApproverHandOffNotification::class);
});

test('rejecting a document sends no email', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $this->engine->submit($doc, $this->sdaoA);
    Notification::assertSentTimes(ApproverHandOffNotification::class, 2); // both SDAO members notified on submit

    $this->engine->reject($doc, $this->sdaoA);

    // No additional notification beyond the initial submit hand-off.
    Notification::assertSentTimes(ApproverHandOffNotification::class, 2);
});

test('a non-quorum SDAO partial approval sends no next-step email', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $this->engine->submit($doc, $this->sdaoA);
    Notification::assertSentTimes(ApproverHandOffNotification::class, 2);

    // First of two required SDAO approvals — should not advance/notify further.
    $this->engine->approve($doc, $this->sdaoA);

    Notification::assertSentTimes(ApproverHandOffNotification::class, 2);
});

test('a first-time submission email reads as a fresh hand-off, not a resubmission', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);

    $this->engine->submit($doc, $this->sdaoA);

    Notification::assertSentTo($this->sdaoA, ApproverHandOffNotification::class, function (ApproverHandOffNotification $notification) {
        $mail = $notification->toMail($this->sdaoA);
        $mail->assertHasSubject("Action needed: {$notification->document->title}");
        $mail->assertSeeInHtml('is waiting for your review', false);
        $mail->assertDontSeeInHtml('previously returned', false);

        return true;
    });
});

test('advancing to the next step emails a fresh hand-off, not a resubmission', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'variant' => ProposalVariant::RegularOnCalendar,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $this->engine->submit($doc, $this->adviser);

    $this->engine->approve($doc, $this->adviser);

    Notification::assertSentTo($this->chair, ApproverHandOffNotification::class, function (ApproverHandOffNotification $notification) {
        $mail = $notification->toMail($this->chair);
        $mail->assertHasSubject("Action needed: {$notification->document->title}");
        $mail->assertSeeInHtml('is waiting for your review', false);
        $mail->assertDontSeeInHtml('previously returned', false);

        return true;
    });
});

test('a resubmission email clearly reads as a resubmission the approver already reviewed and returned', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'variant' => ProposalVariant::RegularOnCalendar,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $this->engine->submit($doc, $this->adviser);
    $this->engine->returnForRevision($doc, $this->adviser, 'Missing signature.');
    $doc->refresh();

    $this->engine->resubmit($doc, $this->adviser);

    Notification::assertSentTo($this->adviser, ApproverHandOffNotification::class, function (ApproverHandOffNotification $notification) use ($doc) {
        if ($notification->stepPosition !== 1 || $notification->document->id !== $doc->id) {
            return false;
        }

        // Distinguish this from the submit-triggered notification also sent
        // to the adviser earlier in this test — only the resubmit one should
        // carry the resubmission wording.
        if ($notification->triggerAction !== TransitionAction::Resubmitted) {
            return false;
        }

        $mail = $notification->toMail($this->adviser);
        $mail->assertHasSubject("Resubmitted for your review: {$notification->document->title}");
        $mail->assertSeeInHtml('You previously returned this', false);
        $mail->assertSeeInHtml('resubmitted and is ready for your review again', false);
        $mail->assertDontSeeInHtml('is waiting for your review', false);

        return true;
    });

    // The original submit-triggered notification (also captured, sent
    // earlier) is untouched — subject/body distinguish the two despite same
    // recipient and step.
    Notification::assertSentTo(
        $this->adviser,
        ApproverHandOffNotification::class,
        fn (ApproverHandOffNotification $notification) => $notification->triggerAction === TransitionAction::Submitted
            && $notification->stepPosition === 1,
    );
    Notification::assertSentTimes(ApproverHandOffNotification::class, 2);
});

test('a notification dispatch failure is logged but does not prevent the submission from succeeding', function () {
    Log::spy();
    Notification::shouldReceive('send')->andThrow(new RuntimeException('smtp boom: 550 5.7.0 Too many emails per second'));

    $doc = Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'variant' => ProposalVariant::RegularOnCalendar,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);

    // Must not throw, even though every notification dispatch fails.
    $this->engine->submit($doc, $this->adviser);

    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::InReview)
        ->and($doc->current_step_position)->toBe(1);

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message) => $message === 'Approver hand-off notification failed to dispatch')
        ->atLeast()->once();
});
