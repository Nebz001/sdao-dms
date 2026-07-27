<?php

use App\Approval\ApprovalEngine;
use App\Approval\SectionFlags;
use App\Attachments\AttachmentSlots;
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

// --- Section-comments redesign: additive per-section notes alongside the
// shared comment and the flat flagged_sections array (see PLAN.md / the
// section-comments design notes). ------------------------------------------

test('section comments persist on the transition alongside flagged sections', function () {
    $doc = sectionFlagTestSubmittedRegistration($this->org, $this->engine, $this->studentAlpha);

    $this->engine->returnForRevision(
        $doc,
        $this->sdaoA,
        'Fix contact info and attachments.',
        ['contact_information', 'attachments'],
        ['contact_information' => 'Phone number is missing.'],
    );

    $transition = DocumentTransition::where('document_id', $doc->id)
        ->where('action', 'returned')
        ->latest('id')
        ->first();

    expect($transition->flagged_sections)->toBe(['contact_information', 'attachments']);
    // Only one of the two flagged sections got a specific note — that's
    // allowed; the shared comment above still covers the other.
    expect($transition->section_comments)->toBe(['contact_information' => 'Phone number is missing.']);
});

test('return without sectionComments still works and stores null', function () {
    $doc = sectionFlagTestSubmittedRegistration($this->org, $this->engine, $this->studentAlpha);

    $this->engine->returnForRevision($doc, $this->sdaoA, 'Please revise.', ['contact_information']);

    $transition = DocumentTransition::where('document_id', $doc->id)
        ->where('action', 'returned')
        ->latest('id')
        ->first();

    expect($transition->flagged_sections)->toBe(['contact_information']);
    expect($transition->section_comments)->toBeNull();
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

// --- Attachment-flagging-by-slot: the registry now emits one flaggable
// section per attachment slot, derived from App\Attachments\AttachmentSlots,
// instead of a single generic "attachments" entry (see PLAN.md). -----------

test('SectionFlags::for() emits one entry per attachment slot instead of a generic "attachments" key', function () {
    $registrationKeys = collect(SectionFlags::for(FormType::OrganizationRegistration))->pluck('key')->all();
    $renewalKeys = collect(SectionFlags::for(FormType::OrganizationRenewal))->pluck('key')->all();
    $reportKeys = collect(SectionFlags::for(FormType::AfterActivityReport))->pluck('key')->all();

    // The old umbrella key no longer exists.
    expect($registrationKeys)->not->toContain('attachments');
    expect($renewalKeys)->not->toContain('attachments');
    expect($reportKeys)->not->toContain('attachments');

    // Every attachment slot key is present instead, sourced from the same
    // registry AttachmentSlotField/AttachmentsCard already use.
    foreach (AttachmentSlots::for(FormType::OrganizationRegistration) as $slot) {
        expect($registrationKeys)->toContain($slot->key);
    }
    foreach (AttachmentSlots::for(FormType::OrganizationRenewal) as $slot) {
        expect($renewalKeys)->toContain($slot->key);
    }
    foreach (AttachmentSlots::for(FormType::AfterActivityReport) as $slot) {
        expect($reportKeys)->toContain($slot->key);
    }

    // Exact counts: 3 form-field sections + N attachment slots + general.
    expect($registrationKeys)->toHaveCount(3 + 6 + 1);
    expect($renewalKeys)->toHaveCount(3 + 9 + 1);
    expect($reportKeys)->toHaveCount(3 + 3 + 1);
});

test('SectionFlags::labelsFor() reuses AttachmentSlots labels verbatim, not a second hand-written copy', function () {
    $labels = SectionFlags::labelsFor(FormType::OrganizationRegistration);

    foreach (AttachmentSlots::for(FormType::OrganizationRegistration) as $slot) {
        expect($labels[$slot->key])->toBe($slot->label);
    }
});

test('Activity Proposal is unaffected — its resource_person key already existed and is untouched', function () {
    $keys = collect(SectionFlags::for(FormType::ActivityProposal))->pluck('key')->all();

    expect($keys)->toContain('resource_person');
    expect($keys)->toHaveCount(9); // unchanged from before this feature
});
