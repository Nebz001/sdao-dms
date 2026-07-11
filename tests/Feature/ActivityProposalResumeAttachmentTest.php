<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Enums\DocumentStatus;
use App\Enums\ProposalCalendarMode;
use App\Models\DocumentAttachment;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Http\UploadedFile;

/**
 * Phase 2 item 8, Mode B — Activity Proposal's one optional attachment slot
 * (Resume of Resource Person(s)), uploaded via the standalone
 * attach-to-existing-document endpoint, independent of step-2 Submit.
 * Always optional, no toggle (decision confirmed with user) — never blocks
 * submission when absent.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $this->studentBeta = User::where('email', 'student-beta@sdao.test')->firstOrFail(); // different org

    $this->document = app(StartProposalDraft::class)->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'title' => 'Resume Attachment Test Activity',
            'venue' => 'Room 100',
            'activity_date' => '2026-11-20',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'term' => 'first_term',
        ],
    );
});

test('uploading a resume while the document is Draft stores it and returns it as JSON', function () {
    $file = UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf');

    $response = $this->actingAs($this->studentAlpha)->post(route('attachments.store'), [
        'document_id' => $this->document->id,
        'slot_key' => 'resume_of_resource_person',
        'file' => $file,
    ]);

    $response->assertCreated();
    $response->assertJsonStructure(['id', 'original_filename', 'download_url']);
    expect($this->document->attachments()->where('slot_key', 'resume_of_resource_person')->count())->toBe(1);
});

test('a second upload to the same slot replaces the first', function () {
    $this->actingAs($this->studentAlpha)->post(route('attachments.store'), [
        'document_id' => $this->document->id,
        'slot_key' => 'resume_of_resource_person',
        'file' => UploadedFile::fake()->create('first.pdf', 50, 'application/pdf'),
    ]);
    $first = $this->document->attachments()->where('slot_key', 'resume_of_resource_person')->firstOrFail();

    $this->actingAs($this->studentAlpha)->post(route('attachments.store'), [
        'document_id' => $this->document->id,
        'slot_key' => 'resume_of_resource_person',
        'file' => UploadedFile::fake()->create('second.pdf', 50, 'application/pdf'),
    ]);

    expect($this->document->attachments()->where('slot_key', 'resume_of_resource_person')->count())->toBe(1);
    expect(DocumentAttachment::find($first->id))->toBeNull();
});

test('destroy removes an uploaded resume', function () {
    $this->actingAs($this->studentAlpha)->post(route('attachments.store'), [
        'document_id' => $this->document->id,
        'slot_key' => 'resume_of_resource_person',
        'file' => UploadedFile::fake()->create('resume.pdf', 50, 'application/pdf'),
    ]);
    $attachment = $this->document->attachments()->where('slot_key', 'resume_of_resource_person')->firstOrFail();

    $response = $this->actingAs($this->studentAlpha)->delete(route('attachments.destroy', $attachment));

    $response->assertNoContent();
    expect(DocumentAttachment::find($attachment->id))->toBeNull();
});

test('a user other than the submitter cannot upload to the document', function () {
    $response = $this->actingAs($this->studentBeta)->post(route('attachments.store'), [
        'document_id' => $this->document->id,
        'slot_key' => 'resume_of_resource_person',
        'file' => UploadedFile::fake()->create('resume.pdf', 50, 'application/pdf'),
    ]);

    $response->assertForbidden();
});

test('upload is forbidden once the document is no longer Draft or Returned', function () {
    $submitAction = app(SubmitActivityProposal::class);
    $submitAction->execute(
        actor: $this->studentAlpha,
        document: $this->document,
        objectives: 'Objectives',
        narrative: 'Narrative',
        criteriaMechanics: 'Criteria',
        programFlow: 'Flow',
        sourceOfFunding: 'Funding',
        expenses: 'Expenses',
    );
    $this->document->refresh();
    expect($this->document->status)->toBe(DocumentStatus::InReview);

    $response = $this->actingAs($this->studentAlpha)->post(route('attachments.store'), [
        'document_id' => $this->document->id,
        'slot_key' => 'resume_of_resource_person',
        'file' => UploadedFile::fake()->create('resume.pdf', 50, 'application/pdf'),
    ]);

    $response->assertForbidden();
});

test('step-2 submit never requires the resume — succeeds with it absent', function () {
    $submitAction = app(SubmitActivityProposal::class);

    $result = $submitAction->execute(
        actor: $this->studentAlpha,
        document: $this->document,
        objectives: 'Objectives',
        narrative: 'Narrative',
        criteriaMechanics: 'Criteria',
        programFlow: 'Flow',
        sourceOfFunding: 'Funding',
        expenses: 'Expenses',
    );

    expect($result['document']->status)->toBe(DocumentStatus::InReview);
    expect($this->document->attachments()->where('slot_key', 'resume_of_resource_person')->count())->toBe(0);
});

test('an uploaded resume appears on the student show page and the reviewer show page', function () {
    $this->actingAs($this->studentAlpha)->post(route('attachments.store'), [
        'document_id' => $this->document->id,
        'slot_key' => 'resume_of_resource_person',
        'file' => UploadedFile::fake()->create('resume.pdf', 50, 'application/pdf'),
    ]);

    $submitAction = app(SubmitActivityProposal::class);
    $submitAction->execute(
        actor: $this->studentAlpha,
        document: $this->document,
        objectives: 'Objectives',
        narrative: 'Narrative',
        criteriaMechanics: 'Criteria',
        programFlow: 'Flow',
        sourceOfFunding: 'Funding',
        expenses: 'Expenses',
    );
    $this->document->refresh();

    $sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();

    // Off-calendar order (CLAUDE.md invariant #8): SDAO (both) is first.
    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('activity-proposals.show', $this->document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('activity-proposals/show')
            ->has('attachments.resume_of_resource_person', 1)
        );

    $this->actingAs($sdaoA)
        ->withoutVite()
        ->get(route('review.activity-proposals.show', $this->document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('review/activity-proposals/show')
            ->has('attachments.resume_of_resource_person', 1)
        );
});
