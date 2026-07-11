<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Models\ActivityCalendar;
use App\Models\ActivityProposal;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\Organization;
use App\Models\User;
use App\Support\AcademicYear;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Http\UploadedFile;

/**
 * Phase 2 item 8 — After-Activity Report's 3 required attachments: Photos
 * (multi-file), Sample Evaluation Form, Attendance Sheet.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
});

function attachmentsTestApprovedProposal(Organization $org, User $actor): ActivityProposal
{
    $engine = app(ApprovalEngine::class);

    $calendarDoc = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Approved Calendar',
        'status' => DocumentStatus::Approved,
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);
    $cal = ActivityCalendar::create([
        'document_id' => $calendarDoc->id,
        'academic_year' => AcademicYear::current(),
        'term' => 'first_term',
    ]);
    $activity = CalendarActivity::create([
        'activity_calendar_id' => $cal->id,
        'name' => 'Attachments Test Activity',
        'venue' => 'Main Hall',
        'activity_date' => '2026-10-30',
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);

    $draft = app(StartProposalDraft::class)->execute(
        actor: $actor,
        organization: $org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );
    ['document' => $proposalDoc] = app(SubmitActivityProposal::class)->execute(
        actor: $actor,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    foreach ([
        'adviser-one@sdao.test', 'chair-cs@sdao.test', 'dean-ccit@sdao.test',
        'sdao-a@sdao.test', 'sdao-b@sdao.test', 'asst-director@sdao.test',
        'academic-director@sdao.test', 'executive-director@sdao.test',
    ] as $email) {
        $engine->approve($proposalDoc, User::where('email', $email)->firstOrFail());
        $proposalDoc->refresh();
    }

    return $proposalDoc->activityProposal()->firstOrFail();
}

function attachmentsTestReportStorePayload(int $proposalId, array $overrides = []): array
{
    return array_merge([
        'activity_proposal_id' => $proposalId,
        'summary' => 'The activity happened as planned.',
        'activity_chairs' => ['Chair One'],
        'prepared_by' => 'Preparer Name',
        'event_program' => 'Program details.',
        'target_participants_percentage' => 85,
    ], $overrides);
}

test('store is blocked when any one of the 3 required attachments is missing', function () {
    $proposal = attachmentsTestApprovedProposal($this->org, $this->studentAlpha);

    foreach (array_keys(reportAttachmentFiles()) as $slotKey) {
        $files = reportAttachmentFiles();
        unset($files[$slotKey]);

        $response = $this->actingAs($this->studentAlpha)->post(route('reports.store'), array_merge(
            attachmentsTestReportStorePayload($proposal->id),
            ['attachments' => $files],
        ));

        $response->assertInvalid(["attachments.{$slotKey}"]);
    }

    expect(Document::where('form_type', FormType::AfterActivityReport->value)->exists())->toBeFalse();
});

test('store succeeds once all 3 required attachments are present', function () {
    $proposal = attachmentsTestApprovedProposal($this->org, $this->studentAlpha);

    $response = $this->actingAs($this->studentAlpha)->post(route('reports.store'), array_merge(
        attachmentsTestReportStorePayload($proposal->id),
        ['attachments' => reportAttachmentFiles()],
    ));

    $response->assertRedirect();

    $report = Document::where('form_type', FormType::AfterActivityReport->value)->firstOrFail();
    expect($report->attachments()->count())->toBe(3);
});

test('Photos accepts multiple files in the same slot', function () {
    $proposal = attachmentsTestApprovedProposal($this->org, $this->studentAlpha);

    $files = reportAttachmentFiles();
    $files['photos'] = [
        UploadedFile::fake()->create('photo-1.jpg', 100, 'image/jpeg'),
        UploadedFile::fake()->create('photo-2.jpg', 100, 'image/jpeg'),
        UploadedFile::fake()->create('photo-3.jpg', 100, 'image/jpeg'),
    ];

    $response = $this->actingAs($this->studentAlpha)->post(route('reports.store'), array_merge(
        attachmentsTestReportStorePayload($proposal->id),
        ['attachments' => $files],
    ));

    $response->assertRedirect();

    $report = Document::where('form_type', FormType::AfterActivityReport->value)->firstOrFail();
    expect($report->attachments()->where('slot_key', 'photos')->count())->toBe(3);
});

test('a single-file slot rejects a second upload by replacing, not appending', function () {
    $proposal = attachmentsTestApprovedProposal($this->org, $this->studentAlpha);

    $this->actingAs($this->studentAlpha)->post(route('reports.store'), array_merge(
        attachmentsTestReportStorePayload($proposal->id),
        ['attachments' => reportAttachmentFiles()],
    ));

    $report = Document::where('form_type', FormType::AfterActivityReport->value)->firstOrFail();
    $original = $report->attachments()->where('slot_key', 'evaluation_form')->firstOrFail();

    // Return + resubmit re-uploading only evaluation_form.
    $sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->engine->returnForRevision($report, $sdaoA, 'Please fix the evaluation form.');
    $report->refresh();

    $this->actingAs($this->studentAlpha)->put(route('reports.update', $report), [
        'summary' => 'The activity happened as planned.',
        'activity_chairs' => ['Chair One'],
        'prepared_by' => 'Preparer Name',
        'event_program' => 'Program details.',
        'target_participants_percentage' => 85,
        'attachments' => [
            'evaluation_form' => UploadedFile::fake()->create('new-eval.pdf', 50, 'application/pdf'),
        ],
    ]);

    $report->refresh();
    expect($report->attachments()->where('slot_key', 'evaluation_form')->count())->toBe(1);
    expect(DocumentAttachment::find($original->id))->toBeNull();
});
