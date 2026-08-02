<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Enums\TransitionAction;
use App\Mail\ApproverHandOffMail;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@sdao.test')->firstOrFail();
    $this->chair = User::where('email', 'chair-cs@sdao.test')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();

    Mail::fake();
});

test('submitting sends a real email to the step-1 approver', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'variant' => ProposalVariant::RegularOnCalendar,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);

    $this->engine->submit($doc, $this->adviser);

    Mail::assertQueued(ApproverHandOffMail::class, function (ApproverHandOffMail $mail) use ($doc) {
        return $mail->hasTo($this->adviser->email)
            && $mail->document->id === $doc->id
            && $mail->stepPosition === 1;
    });
});

test('submitting a short-chain document emails both SDAO members', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);

    $this->engine->submit($doc, $this->sdaoA);

    Mail::assertQueued(ApproverHandOffMail::class, fn (ApproverHandOffMail $mail) => $mail->hasTo($this->sdaoA->email));
    Mail::assertQueued(ApproverHandOffMail::class, fn (ApproverHandOffMail $mail) => $mail->hasTo($this->sdaoB->email));
    Mail::assertQueued(ApproverHandOffMail::class, 2);
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

    Mail::assertQueued(ApproverHandOffMail::class, function (ApproverHandOffMail $mail) {
        return $mail->hasTo($this->chair->email) && $mail->stepPosition === 2;
    });
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
    Mail::assertQueued(ApproverHandOffMail::class, 2);
    Mail::assertQueued(ApproverHandOffMail::class, fn (ApproverHandOffMail $mail) => $mail->hasTo($this->adviser->email));
});

test('rejecting a document sends no email', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $this->engine->submit($doc, $this->sdaoA);
    Mail::assertQueued(ApproverHandOffMail::class, 2); // both SDAO members notified on submit

    $this->engine->reject($doc, $this->sdaoA);

    // No additional mail beyond the initial submit hand-off.
    Mail::assertQueued(ApproverHandOffMail::class, 2);
});

test('a non-quorum SDAO partial approval sends no next-step email', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $this->engine->submit($doc, $this->sdaoA);
    Mail::assertQueued(ApproverHandOffMail::class, 2);

    // First of two required SDAO approvals — should not advance/notify further.
    $this->engine->approve($doc, $this->sdaoA);

    Mail::assertQueued(ApproverHandOffMail::class, 2);
});

test('a first-time submission email reads as a fresh hand-off, not a resubmission', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);

    $this->engine->submit($doc, $this->sdaoA);

    Mail::assertQueued(ApproverHandOffMail::class, function (ApproverHandOffMail $mail) {
        $mail->assertHasSubject("Action needed: {$mail->document->title}");
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

    Mail::assertQueued(ApproverHandOffMail::class, function (ApproverHandOffMail $mail) {
        if (! $mail->hasTo($this->chair->email)) {
            return false;
        }

        $mail->assertHasSubject("Action needed: {$mail->document->title}");
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

    Mail::assertQueued(ApproverHandOffMail::class, function (ApproverHandOffMail $mail) use ($doc) {
        if ($mail->stepPosition !== 1 || $mail->document->id !== $doc->id) {
            return false;
        }

        // Distinguish this from the submit-triggered mail also queued for
        // the adviser earlier in this test — only the resubmit one should
        // carry the resubmission wording.
        if ($mail->triggerAction !== TransitionAction::Resubmitted) {
            return false;
        }

        $mail->assertHasSubject("Resubmitted for your review: {$mail->document->title}");
        $mail->assertSeeInHtml('You previously returned this', false);
        $mail->assertSeeInHtml('resubmitted and is ready for your review again', false);
        $mail->assertDontSeeInHtml('is waiting for your review', false);

        return true;
    });

    // The original submit-triggered mail (still queued, sent earlier) is
    // untouched — subject/body distinguish the two despite same recipient
    // and step.
    Mail::assertQueued(ApproverHandOffMail::class, fn (ApproverHandOffMail $mail) => $mail->triggerAction === TransitionAction::Submitted
        && $mail->stepPosition === 1
    );
    Mail::assertQueued(ApproverHandOffMail::class, 2);
});

test('a mail dispatch failure is logged but does not prevent the submission from succeeding', function () {
    Log::spy();

    $pending = Mockery::mock(PendingMail::class);
    $pending->shouldReceive('queue')->andThrow(new RuntimeException('smtp boom: 550 5.7.0 Too many emails per second'));
    Mail::shouldReceive('to')->andReturn($pending);

    $doc = Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'variant' => ProposalVariant::RegularOnCalendar,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);

    // Must not throw, even though every mail dispatch fails.
    $this->engine->submit($doc, $this->adviser);

    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::InReview)
        ->and($doc->current_step_position)->toBe(1);

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message) => $message === 'Approver hand-off notification failed to dispatch')
        ->atLeast()->once();
});
