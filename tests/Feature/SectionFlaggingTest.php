<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Models\Document;
use App\Models\DocumentStepApproval;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Phase 2 item 9 — engine-level proof that flaggedSections is purely
 * additive on ApprovalEngine::returnForRevision(): it persists on the
 * transition, defaults to null when omitted, and changes NOTHING about the
 * resume-at-requester-by-rank / lower-step-approval-persistence behavior
 * that ReturnAndResubmitTest.php and ProposalReturnForRevisionTest.php
 * already prove for the unflagged case.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
});

function sectionFlagTestSubmittedRegistration(Organization $org, ApprovalEngine $engine, User $submitter): Document
{
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $submitter->id,
    ]);
    OrganizationRegistrationDetail::factory()->create([
        'document_id' => $doc->id,
        'organization_type' => OrganizationType::CoCurricular,
    ]);
    $engine->submit($doc, $submitter);
    $doc->refresh();

    return $doc;
}

test('flagged sections persist on the transition', function () {
    $doc = sectionFlagTestSubmittedRegistration($this->org, $this->engine, $this->studentAlpha);

    $this->engine->returnForRevision(
        $doc,
        $this->sdaoA,
        'Fix contact info and attachments.',
        ['contact_information', 'attachments'],
    );

    $transition = DocumentTransition::where('document_id', $doc->id)
        ->where('action', 'returned')
        ->latest('id')
        ->first();

    expect($transition->flagged_sections)->toBe(['contact_information', 'attachments']);
});

test('return without flaggedSections still works and stores null', function () {
    $doc = sectionFlagTestSubmittedRegistration($this->org, $this->engine, $this->studentAlpha);

    $this->engine->returnForRevision($doc, $this->sdaoA, 'Please revise.');

    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::Returned);

    $transition = DocumentTransition::where('document_id', $doc->id)
        ->where('action', 'returned')
        ->latest('id')
        ->first();

    expect($transition->flagged_sections)->toBeNull();
});

test('flagging sections does not change resume-at-requester or lower-step-approval persistence', function () {
    // Mirrors ReturnAndResubmitTest's "resubmit resumes at SDAO step" scenario,
    // but with flagged sections attached — the only difference from the
    // unflagged test should be the flagged_sections column itself.
    $doc = sectionFlagTestSubmittedRegistration($this->org, $this->engine, $this->studentAlpha);

    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();

    // Partial approval recorded for the SDAO step before the return.
    $partialsBefore = DocumentStepApproval::where('document_id', $doc->id)->count();
    expect($partialsBefore)->toBe(1);

    $this->engine->returnForRevision($doc, $this->sdaoB, 'Needs work.', ['organization_details']);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Returned);
    expect($doc->current_step_position)->toBe(1);

    // The partial SDAO-A approval is cleared just like the unflagged case
    // (both SDAO members sit on the same step, so the step-level clear
    // removes it) — resume-at-requester behavior is unaffected by flags.
    $partialsAfter = DocumentStepApproval::where('document_id', $doc->id)->count();
    expect($partialsAfter)->toBe(0);

    $this->engine->resubmit($doc, $this->studentAlpha);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::InReview);
    expect($doc->current_step_position)->toBe(1);

    // Both must re-approve, exactly as in the unflagged scenario.
    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::InReview);

    $this->engine->approve($doc, $this->sdaoB);
    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::Approved);
});
