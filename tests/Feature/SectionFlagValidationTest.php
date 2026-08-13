<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Approval\SectionFlags;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\User;
use App\Support\AcademicYear;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Phase 2 item 9 — HTTP-level proof that ReviewActionRequest's `sections`
 * validation (App\Approval\SectionFlags::validKeysFor) actually enforces the
 * per-form-type key set, that Reject never validates `sections` at all (it's
 * truly inert there, not merely unpersisted), and that Activity Calendar's
 * dynamic row-count keys work correctly.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
});

/**
 * Builds a minimal InReview document for one of the SDAO-only short-chain
 * form types (Registration, Renewal, After-Activity Report), bypassing the
 * form-specific Submit action classes entirely — section-key validation
 * only needs a real Document row at step 1, not a fully populated detail
 * record.
 */
function shortChainInReviewDoc(FormType $formType, Organization $org, ApprovalEngine $engine, User $submitter): Document
{
    $doc = Document::factory()->create([
        'form_type' => $formType,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $submitter->id,
    ]);
    $engine->submit($doc, $submitter);
    $doc->refresh();

    return $doc;
}

function calendarInReviewDocWithActivities(Organization $org, ApprovalEngine $engine, User $submitter, int $activityCount): Document
{
    $doc = Document::factory()->create([
        'form_type' => FormType::ActivityCalendar,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $submitter->id,
    ]);
    $calendar = ActivityCalendar::create([
        'document_id' => $doc->id,
        'academic_year' => AcademicYear::current(),
        'term' => 'first_term',
    ]);

    for ($i = 0; $i < $activityCount; $i++) {
        CalendarActivity::create([
            'activity_calendar_id' => $calendar->id,
            'name' => "Validation Test Activity {$i}",
            'venue' => "Venue {$i}",
            'activity_date' => '2026-12-05',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);
    }

    $engine->submit($doc, $submitter);
    $doc->refresh();

    return $doc;
}

// --- Unknown section keys are rejected -------------------------------------

test('return rejects an unknown section key for Registration', function () {
    $doc = shortChainInReviewDoc(FormType::OrganizationRegistration, $this->org, $this->engine, $this->studentAlpha);

    $this->actingAs($this->sdaoA)
        ->post(route('review.registrations.return', $doc), [
            'comment' => 'Fix this.',
            'sections' => ['not_a_real_section'],
        ])
        ->assertInvalid(['sections.0']);
});

test('return rejects an unknown section key for After-Activity Report', function () {
    $doc = shortChainInReviewDoc(FormType::AfterActivityReport, $this->org, $this->engine, $this->studentAlpha);

    $this->actingAs($this->sdaoA)
        ->post(route('review.reports.return', $doc), [
            'comment' => 'Fix this.',
            'sections' => ['bogus_key'],
        ])
        ->assertInvalid(['sections.0']);
});

test('return rejects an activity index beyond the current row count for Activity Calendar', function () {
    $doc = calendarInReviewDocWithActivities($this->org, $this->engine, $this->studentAlpha, 2);

    $this->actingAs($this->sdaoA)
        ->post(route('review.activity-calendars.return', $doc), [
            'comment' => 'Fix row 3, which does not exist.',
            'sections' => ['activity_2'],
        ])
        ->assertInvalid(['sections.0']);
});

// --- Attachment-flagging-by-slot: the old generic "attachments" key no
// longer exists — documents the intentional removal so it can't silently
// come back (see PLAN.md). ---------------------------------------------------

test('return rejects the old generic "attachments" key for Registration', function () {
    $doc = shortChainInReviewDoc(FormType::OrganizationRegistration, $this->org, $this->engine, $this->studentAlpha);

    $this->actingAs($this->sdaoA)
        ->post(route('review.registrations.return', $doc), [
            'comment' => 'Fix this.',
            'sections' => ['attachments'],
        ])
        ->assertInvalid(['sections.0']);
});

test('return rejects the old generic "attachments" key for Renewal', function () {
    $doc = shortChainInReviewDoc(FormType::OrganizationRenewal, $this->org, $this->engine, $this->studentAlpha);

    $this->actingAs($this->sdaoA)
        ->post(route('review.renewals.return', $doc), [
            'comment' => 'Fix this.',
            'sections' => ['attachments'],
        ])
        ->assertInvalid(['sections.0']);
});

test('return rejects the old generic "attachments" key for After-Activity Report', function () {
    $doc = shortChainInReviewDoc(FormType::AfterActivityReport, $this->org, $this->engine, $this->studentAlpha);

    $this->actingAs($this->sdaoA)
        ->post(route('review.reports.return', $doc), [
            'comment' => 'Fix this.',
            'sections' => ['attachments'],
        ])
        ->assertInvalid(['sections.0']);
});

// --- Every valid key is accepted --------------------------------------------

test('return accepts every valid section key for Registration', function () {
    $doc = shortChainInReviewDoc(FormType::OrganizationRegistration, $this->org, $this->engine, $this->studentAlpha);
    $allKeys = collect(SectionFlags::for(FormType::OrganizationRegistration))->pluck('key')->all();

    $this->actingAs($this->sdaoA)
        ->post(route('review.registrations.return', $doc), [
            'comment' => 'Please fix everything.',
            'sections' => $allKeys,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('review.registrations.show', $doc));

    expect($doc->refresh()->status)->toBe(DocumentStatus::Returned);
});

test('return accepts every valid section key for Renewal', function () {
    $doc = shortChainInReviewDoc(FormType::OrganizationRenewal, $this->org, $this->engine, $this->studentAlpha);
    $allKeys = collect(SectionFlags::for(FormType::OrganizationRenewal))->pluck('key')->all();

    $this->actingAs($this->sdaoA)
        ->post(route('review.renewals.return', $doc), [
            'comment' => 'Please fix everything.',
            'sections' => $allKeys,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('review.renewals.show', $doc));

    expect($doc->refresh()->status)->toBe(DocumentStatus::Returned);
});

test('return accepts every valid section key for After-Activity Report', function () {
    $doc = shortChainInReviewDoc(FormType::AfterActivityReport, $this->org, $this->engine, $this->studentAlpha);
    $allKeys = collect(SectionFlags::for(FormType::AfterActivityReport))->pluck('key')->all();

    $this->actingAs($this->sdaoA)
        ->post(route('review.reports.return', $doc), [
            'comment' => 'Please fix everything.',
            'sections' => $allKeys,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('review.reports.show', $doc));

    expect($doc->refresh()->status)->toBe(DocumentStatus::Returned);
});

test('return accepts every valid section key for Activity Proposal (combined 9-key union)', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    $startDraft = app(StartProposalDraft::class);
    $submitProposal = app(SubmitActivityProposal::class);

    $calendarDoc = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Approved Calendar',
        'status' => DocumentStatus::Approved,
        'current_step_position' => null,
        'organization_id' => $this->org->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);
    $calendar = ActivityCalendar::create([
        'document_id' => $calendarDoc->id,
        'academic_year' => AcademicYear::current(),
        'term' => 'first_term',
    ]);
    $activity = CalendarActivity::create([
        'activity_calendar_id' => $calendar->id,
        'name' => 'Section Flag Validation Activity',
        'venue' => 'Validation Hall',
        'activity_date' => '2026-12-05',
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);

    $draft = $startDraft->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );

    ['document' => $doc] = $submitProposal->execute(
        actor: $this->studentAlpha,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    $allKeys = collect(SectionFlags::for(FormType::ActivityProposal))->pluck('key')->all();
    expect($allKeys)->toHaveCount(9);

    $this->actingAs($adviser)
        ->post(route('review.activity-proposals.return', $doc), [
            'comment' => 'Please fix everything.',
            'sections' => $allKeys,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('review.activity-proposals.show', $doc));

    expect($doc->refresh()->status)->toBe(DocumentStatus::Returned);
});

test('return accepts activity_0 and activity_1 for a 2-row Activity Calendar', function () {
    $doc = calendarInReviewDocWithActivities($this->org, $this->engine, $this->studentAlpha, 2);

    $this->actingAs($this->sdaoA)
        ->post(route('review.activity-calendars.return', $doc), [
            'comment' => 'Fix both rows.',
            'sections' => ['activity_0', 'activity_1'],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('review.activity-calendars.show', $doc));

    expect($doc->refresh()->status)->toBe(DocumentStatus::Returned);
});

// --- section_comments must be a subset of the flagged sections in the same
// request (section-comments redesign — see PLAN.md) -------------------------

test('return rejects a section comment for a section that was not flagged', function () {
    $doc = shortChainInReviewDoc(FormType::OrganizationRegistration, $this->org, $this->engine, $this->studentAlpha);

    $this->actingAs($this->sdaoA)
        ->post(route('review.registrations.return', $doc), [
            'comment' => 'Fix this.',
            'sections' => ['contact_information'],
            'section_comments' => ['by_laws' => 'Missing the by-laws PDF.'],
        ])
        ->assertInvalid(['section_comments']);
});

test('return accepts a section comment for a section that was flagged, and persists it', function () {
    $doc = shortChainInReviewDoc(FormType::OrganizationRegistration, $this->org, $this->engine, $this->studentAlpha);

    $this->actingAs($this->sdaoA)
        ->post(route('review.registrations.return', $doc), [
            'comment' => 'Fix these two things.',
            'sections' => ['contact_information', 'by_laws'],
            'section_comments' => ['contact_information' => 'Phone number is missing.'],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('review.registrations.show', $doc));

    $transition = DocumentTransition::where('document_id', $doc->id)
        ->where('action', 'returned')
        ->latest('id')
        ->first();

    expect($transition->section_comments)->toBe(['contact_information' => 'Phone number is missing.']);
    // By-Laws was flagged but got no specific note — allowed; the shared
    // comment above still covers it.
    expect($transition->flagged_sections)->toBe(['contact_information', 'by_laws']);
});

test('return works with no section_comments at all, even when sections are flagged', function () {
    $doc = shortChainInReviewDoc(FormType::OrganizationRegistration, $this->org, $this->engine, $this->studentAlpha);

    $this->actingAs($this->sdaoA)
        ->post(route('review.registrations.return', $doc), [
            'comment' => 'Fix these.',
            'sections' => ['contact_information', 'by_laws'],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('review.registrations.show', $doc));

    expect($doc->refresh()->status)->toBe(DocumentStatus::Returned);
});

// --- Reject never validates `sections` — truly inert, not just unpersisted -

test('reject succeeds with a sections array in the payload and the document ends up Rejected', function () {
    $doc = shortChainInReviewDoc(FormType::OrganizationRegistration, $this->org, $this->engine, $this->studentAlpha);

    $this->actingAs($this->sdaoA)
        ->post(route('review.registrations.reject', $doc), [
            'comment' => 'Rejected for unrelated reasons.',
            'sections' => ['this_is_not_even_validated', 'nor_is_this'],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('review.registrations.index'));

    expect($doc->refresh()->status)->toBe(DocumentStatus::Rejected);
});
