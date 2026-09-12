<?php

use App\ActivityProposals\ResubmitActivityProposal;
use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Approval\SectionFlags;
use App\Attachments\AttachmentSlots;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Enums\TransitionAction;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Group C item 3 — Request Letter and Sample Post-Survey Form (new,
 * required) plus Resume of Resource Person(s) (existing, optional,
 * relocated from step 2) become real Mode A bundled attachments at step 1,
 * same pattern as Registration/Renewal's required attachments.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->startDraft = app(StartProposalDraft::class);
    $this->submitProposal = app(SubmitActivityProposal::class);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    // On-calendar, regular school: step 1 is the adviser, not SDAO
    // (invariant #8) — this is who must act to return the document.
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
});

function step1AttachmentsApprovedActivity(Organization $org, string $name = 'Step 1 Attachments Test Event'): CalendarActivity
{
    $doc = Document::create([
        'form_type' => 'activity_calendar',
        'variant' => null,
        'title' => 'Approved Calendar',
        'status' => 'approved',
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);
    $cal = ActivityCalendar::create(['document_id' => $doc->id, 'academic_year' => '2026-2027', 'term' => 'first_term']);

    return CalendarActivity::create([
        'activity_calendar_id' => $cal->id,
        'name' => $name,
        'venue' => 'Auditorium',
        'activity_date' => '2026-10-25',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ]);
}

// --- The slot registry itself -------------------------------------------

test('all 3 ActivityProposal attachment slots are step 1; step 2 has none', function () {
    $step1 = AttachmentSlots::for(FormType::ActivityProposal, 1);
    $step2 = AttachmentSlots::for(FormType::ActivityProposal, 2);
    $unscoped = AttachmentSlots::for(FormType::ActivityProposal);

    expect(collect($step1)->pluck('key')->all())
        ->toBe(['request_letter', 'resume_of_resource_person', 'sample_post_survey_form']);
    expect($step2)->toBe([]);
    expect(collect($unscoped)->pluck('key')->all())
        ->toBe(['request_letter', 'resume_of_resource_person', 'sample_post_survey_form']);
});

test('Request Letter and Sample Post-Survey Form are required; Resume of Resource Person(s) is optional', function () {
    $slots = collect(AttachmentSlots::for(FormType::ActivityProposal, 1))->keyBy('key');

    expect($slots['request_letter']->required)->toBeTrue();
    expect($slots['sample_post_survey_form']->required)->toBeTrue();
    expect($slots['resume_of_resource_person']->required)->toBeFalse();
});

test('all 3 slots are individually section-flaggable, derived generically like every other form type\'s attachments', function () {
    $keys = collect(SectionFlags::for(FormType::ActivityProposal))->pluck('key');

    expect($keys)->toContain('request_letter');
    expect($keys)->toContain('resume_of_resource_person');
    expect($keys)->toContain('sample_post_survey_form');
});

// --- Store validation -----------------------------------------------------

test('store is blocked when Request Letter or Sample Post-Survey Form is missing', function () {
    $activity = step1AttachmentsApprovedActivity($this->org);

    foreach (['request_letter', 'sample_post_survey_form'] as $slotKey) {
        $files = proposalStepOneAttachmentFiles();
        unset($files[$slotKey]);

        $response = $this->actingAs($this->student)->post(route('activity-proposals.store'), array_merge(
            ['calendar_mode' => 'on_calendar', 'calendar_activity_id' => $activity->id],
            step1ExactFields(),
            ['attachments' => $files],
        ));

        $response->assertInvalid(["attachments.{$slotKey}"]);
    }

    expect(Document::where('form_type', FormType::ActivityProposal->value)->exists())->toBeFalse();
});

test('store succeeds without Resume of Resource Person(s) — it is optional', function () {
    $activity = step1AttachmentsApprovedActivity($this->org);

    $response = $this->actingAs($this->student)->post(route('activity-proposals.store'), array_merge(
        ['calendar_mode' => 'on_calendar', 'calendar_activity_id' => $activity->id],
        step1ExactFields(),
        ['attachments' => proposalStepOneAttachmentFiles()],
    ));

    $response->assertRedirect();

    $document = Document::where('form_type', FormType::ActivityProposal->value)->latest('id')->firstOrFail();
    expect($document->attachments()->count())->toBe(2);
    expect($document->attachments()->where('slot_key', 'resume_of_resource_person')->exists())->toBeFalse();
});

test('all 3 files persist with the correct slot keys when all are provided', function () {
    $activity = step1AttachmentsApprovedActivity($this->org);

    $files = array_merge(proposalStepOneAttachmentFiles(), [
        'resume_of_resource_person' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
    ]);

    $response = $this->actingAs($this->student)->post(route('activity-proposals.store'), array_merge(
        ['calendar_mode' => 'on_calendar', 'calendar_activity_id' => $activity->id],
        step1ExactFields(),
        ['attachments' => $files],
    ));

    $response->assertRedirect();

    $document = Document::where('form_type', FormType::ActivityProposal->value)->latest('id')->firstOrFail();
    $slotKeys = $document->attachments()->pluck('slot_key')->sort()->values()->all();

    expect($slotKeys)->toBe(['request_letter', 'resume_of_resource_person', 'sample_post_survey_form']);
});

// --- Step 2 no longer shows these attachments (the explicit requirement) --

test('step 2 (continue) no longer renders any attachment slots or files — they all moved to step 1', function () {
    $activity = step1AttachmentsApprovedActivity($this->org);

    $document = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: array_merge(['calendar_activity_id' => $activity->id], step1ExactFields()),
        attachmentFiles: array_merge(proposalStepOneAttachmentFiles(), [
            'resume_of_resource_person' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
        ]),
    );

    $this->actingAs($this->student)
        ->withoutVite()
        ->get(route('activity-proposals.continue', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('activity-proposals/step-two')
            ->missing('attachmentSlots')
            ->missing('attachments')
        );
});

// --- Resubmit -------------------------------------------------------------

test('resubmit preserves untouched attachment slots and replaces a flagged one', function () {
    $activity = step1AttachmentsApprovedActivity($this->org);

    $document = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: array_merge(['calendar_activity_id' => $activity->id], step1ExactFields()),
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );
    $this->submitProposal->execute(actor: $this->student, document: $document, objectives: 'Overall Goal');
    $document->refresh();

    $originalRequestLetter = $document->attachments()->where('slot_key', 'request_letter')->firstOrFail();

    $this->engine->returnForRevision($document, $this->adviser, 'Please update the request letter.', flaggedSections: ['request_letter']);
    $document->refresh();

    $response = $this->actingAs($this->student)->put(route('activity-proposals.update', $document), [
        'objectives' => 'Overall Goal',
        'activity_description' => 'Activity Description',
        'criteria_mechanics' => 'Criteria',
        'program_flow' => 'Program flow',
        'expense_items' => [['material' => 'Expenses', 'quantity' => '1', 'unit_price' => '100.00']],
        'responsible_persons' => ['Responsible Person'],
        'attachments' => [
            'request_letter' => UploadedFile::fake()->create('new-request-letter.pdf', 100, 'application/pdf'),
        ],
    ]);

    $response->assertRedirect();
    $document->refresh();

    expect($document->attachments()->where('slot_key', 'sample_post_survey_form')->count())->toBe(1);
    expect($document->attachments()->where('slot_key', 'request_letter')->count())->toBe(1);
    expect(DocumentAttachment::find($originalRequestLetter->id))->toBeNull();

    // Document::transitions() already applies ->orderBy('id') ascending;
    // chaining ->latest('id') on the same column is a no-op (the first
    // ORDER BY clause on a unique column wins), so it would silently return
    // the FIRST transition, not the last. Filter by the specific action
    // instead — there's exactly one Resubmitted transition in this flow.
    $latestTransition = $document->transitions()->where('action', TransitionAction::Resubmitted)->first();
    expect($latestTransition->field_changes)->not->toBeNull();
    expect($latestTransition->field_changes['request_letter']['status'] ?? null)->toBe('replaced');
});

test('resubmit still enforces required slots — a resubmit that somehow leaves one empty is blocked', function () {
    $activity = step1AttachmentsApprovedActivity($this->org);

    $document = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: array_merge(['calendar_activity_id' => $activity->id], step1ExactFields()),
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );
    $this->submitProposal->execute(actor: $this->student, document: $document, objectives: 'Overall Goal');
    $document->refresh();

    // Directly delete the persisted attachment to simulate a document that
    // somehow lost a required slot's file before resubmit — the action-level
    // completeness gate must still catch this, independent of the
    // FormRequest's own (deliberately non-required-on-resubmit) rules.
    $document->attachments()->where('slot_key', 'sample_post_survey_form')->delete();

    $this->engine->returnForRevision($document, $this->adviser, 'Please revise.', flaggedSections: ['general']);
    $document->refresh();

    $action = app(ResubmitActivityProposal::class);

    expect(fn () => $action->execute($this->student, $document, [
        'objectives' => 'Overall Goal',
        'activity_description' => 'Activity Description',
        'criteria_mechanics' => 'Criteria',
        'program_flow' => 'Program flow',
        'expense_items' => [['material' => 'Expenses', 'quantity' => '1', 'unit_price' => '100.00']],
        'responsible_persons' => ['Responsible Person'],
    ]))->toThrow(ValidationException::class);
});
