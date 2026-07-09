<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Mail\ApproverHandOffMail;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\WorkflowTemplateSeeder;
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

    Mail::assertSent(ApproverHandOffMail::class, function (ApproverHandOffMail $mail) use ($doc) {
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

    Mail::assertSent(ApproverHandOffMail::class, fn (ApproverHandOffMail $mail) => $mail->hasTo($this->sdaoA->email));
    Mail::assertSent(ApproverHandOffMail::class, fn (ApproverHandOffMail $mail) => $mail->hasTo($this->sdaoB->email));
    Mail::assertSent(ApproverHandOffMail::class, 2);
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

    Mail::assertSent(ApproverHandOffMail::class, function (ApproverHandOffMail $mail) {
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
    Mail::assertSent(ApproverHandOffMail::class, 2);
    Mail::assertSent(ApproverHandOffMail::class, fn (ApproverHandOffMail $mail) => $mail->hasTo($this->adviser->email));
});

test('rejecting a document sends no email', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $this->engine->submit($doc, $this->sdaoA);
    Mail::assertSent(ApproverHandOffMail::class, 2); // both SDAO members notified on submit

    $this->engine->reject($doc, $this->sdaoA);

    // No additional mail beyond the initial submit hand-off.
    Mail::assertSent(ApproverHandOffMail::class, 2);
});

test('a non-quorum SDAO partial approval sends no next-step email', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $this->engine->submit($doc, $this->sdaoA);
    Mail::assertSent(ApproverHandOffMail::class, 2);

    // First of two required SDAO approvals — should not advance/notify further.
    $this->engine->approve($doc, $this->sdaoA);

    Mail::assertSent(ApproverHandOffMail::class, 2);
});
