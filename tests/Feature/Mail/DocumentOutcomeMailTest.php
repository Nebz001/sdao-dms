<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Mail\DocumentOutcomeMail;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

// Deliberately does NOT seed MembershipSeeder — $this->org has no active
// officers, and $this->student is an unaffiliated User::factory() account
// (not bound to $this->org at all). Every test below therefore exercises the
// fallback branch of ApprovalEngine::notifySubmitter() (no active officers ->
// notify $document->submitter alone) — the same branch a founding
// registration takes, since ApproveOrganizationRegistration only creates the
// OrganizationMembership AFTER SDAO quorum. See the "...emails both active
// officers..." tests below for the has-officers branch.
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@sdao.test')->firstOrFail();
    $this->chair = User::where('email', 'chair-cs@sdao.test')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $this->student = User::factory()->create();

    Mail::fake();
});

test('final quorum approval emails the submitter with the Approved outcome', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $this->student->id,
    ]);
    $this->engine->submit($doc, $this->student);

    $this->engine->approve($doc, $this->sdaoA);
    $this->engine->approve($doc, $this->sdaoB);

    Mail::assertQueued(DocumentOutcomeMail::class, function (DocumentOutcomeMail $mail) use ($doc) {
        return $mail->hasTo($this->student->email)
            && $mail->document->id === $doc->id
            && $mail->outcome === DocumentStatus::Approved;
    });
    Mail::assertQueued(DocumentOutcomeMail::class, 1);
});

test('a non-quorum SDAO partial approval sends no submitter email', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $this->student->id,
    ]);
    $this->engine->submit($doc, $this->student);

    // First of two required SDAO approvals — quorum not yet met.
    $this->engine->approve($doc, $this->sdaoA);

    Mail::assertNotQueued(DocumentOutcomeMail::class);
});

test('advancing to a non-final step sends no submitter email', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'variant' => ProposalVariant::RegularOnCalendar,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $this->student->id,
    ]);
    $this->engine->submit($doc, $this->student);

    // Advances from step 1 (adviser) to step 2 (chair) — not the final step.
    $this->engine->approve($doc, $this->adviser);

    Mail::assertNotQueued(DocumentOutcomeMail::class);
});

test('rejecting a document emails the submitter with the Rejected outcome and comment', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $this->student->id,
    ]);
    $this->engine->submit($doc, $this->student);

    $this->engine->reject($doc, $this->sdaoA, 'Missing required attachments.');

    Mail::assertQueued(DocumentOutcomeMail::class, function (DocumentOutcomeMail $mail) use ($doc) {
        return $mail->hasTo($this->student->email)
            && $mail->document->id === $doc->id
            && $mail->outcome === DocumentStatus::Rejected
            && $mail->comment === 'Missing required attachments.';
    });
});

test('returning a document for revision emails the submitter with the Returned outcome and comment', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $this->student->id,
    ]);
    $this->engine->submit($doc, $this->student);

    $this->engine->returnForRevision($doc, $this->sdaoA, 'Please fix the adviser section.', ['adviser_selection']);

    Mail::assertQueued(DocumentOutcomeMail::class, function (DocumentOutcomeMail $mail) use ($doc) {
        return $mail->hasTo($this->student->email)
            && $mail->document->id === $doc->id
            && $mail->outcome === DocumentStatus::Returned
            && $mail->comment === 'Please fix the adviser section.';
    });
});

test('resubmit sends no submitter email — that channel is approver-only', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $this->student->id,
    ]);
    $this->engine->submit($doc, $this->student);
    $this->engine->returnForRevision($doc, $this->sdaoA, 'Fix this.');
    $doc->refresh();

    $this->engine->resubmit($doc, $this->student);

    // Exactly the one Returned email from above — nothing new on resubmit.
    Mail::assertQueued(DocumentOutcomeMail::class, 1);
});

test('a document with no recorded submitter notifies nobody and does not throw', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => null,
    ]);
    $this->engine->submit($doc, $this->sdaoA);

    $this->engine->reject($doc, $this->sdaoA);

    Mail::assertNotQueued(DocumentOutcomeMail::class);
});

test('a mail dispatch failure is logged but does not prevent the rejection from succeeding', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $this->student->id,
    ]);
    $this->engine->submit($doc, $this->student);

    Log::spy();

    $pending = Mockery::mock(PendingMail::class);
    $pending->shouldReceive('queue')->andThrow(new RuntimeException('smtp boom: 550 5.7.0 Too many emails per second'));
    Mail::shouldReceive('to')->andReturn($pending);

    // Must not throw, even though the mail dispatch fails.
    $this->engine->reject($doc, $this->sdaoA, 'Duplicate submission.');

    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::Rejected);

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message) => $message === 'Submitter outcome notification failed to dispatch')
        ->atLeast()->once();
});

test('the document URL in the mail points at the student-facing show route, not the review route', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $this->student->id,
    ]);
    $this->engine->submit($doc, $this->student);

    $this->engine->reject($doc, $this->sdaoA);

    Mail::assertQueued(DocumentOutcomeMail::class, function (DocumentOutcomeMail $mail) use ($doc) {
        $mail->assertSeeInHtml(route('registrations.show', $doc), false);
        $mail->assertDontSeeInHtml(route('review.registrations.show', $doc), false);

        return true;
    });
});

// --- Shared officer ownership: both active officers are notified, not just submitted_by ---

test('returning a document for revision emails both active officers, not just the submitter', function () {
    $this->seed(MembershipSeeder::class);
    $president = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $secretary = User::where('email', 'student-delta@sdao.test')->firstOrFail();

    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $president->id,
    ]);
    $this->engine->submit($doc, $president);

    $this->engine->returnForRevision($doc, $this->sdaoA, 'Please fix the adviser section.');

    Mail::assertQueued(DocumentOutcomeMail::class, fn (DocumentOutcomeMail $mail) => $mail->hasTo($president->email) && $mail->outcome === DocumentStatus::Returned);
    Mail::assertQueued(DocumentOutcomeMail::class, fn (DocumentOutcomeMail $mail) => $mail->hasTo($secretary->email) && $mail->outcome === DocumentStatus::Returned);
    Mail::assertQueued(DocumentOutcomeMail::class, 2);
});

test('rejecting a document emails both active officers when the org has them', function () {
    $this->seed(MembershipSeeder::class);
    $president = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $secretary = User::where('email', 'student-delta@sdao.test')->firstOrFail();

    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $secretary->id,
    ]);
    $this->engine->submit($doc, $secretary);

    $this->engine->reject($doc, $this->sdaoA, 'Duplicate submission.');

    Mail::assertQueued(DocumentOutcomeMail::class, fn (DocumentOutcomeMail $mail) => $mail->hasTo($president->email));
    Mail::assertQueued(DocumentOutcomeMail::class, fn (DocumentOutcomeMail $mail) => $mail->hasTo($secretary->email));
    Mail::assertQueued(DocumentOutcomeMail::class, 2);
});
