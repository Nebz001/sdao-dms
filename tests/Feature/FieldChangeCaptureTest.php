<?php

use App\ActivityProposals\ResubmitActivityProposal;
use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Attachments\AttachmentStorage;
use App\Calendar\SubmitActivityCalendar;
use App\Calendar\UpdateActivityCalendar;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Enums\ProposalCalendarMode;
use App\Enums\TransitionAction;
use App\Models\ActivityProposal;
use App\Models\AfterActivityReport;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use App\Registrations\UpdateOrganizationRegistration;
use App\Reports\UpdateAfterActivityReport;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Http\UploadedFile;

/**
 * Field-level revision diffs — proof that a resubmission captures the
 * before/after values of exactly the fields belonging to the sections the
 * approver flagged, and nothing else. See App\Approval\FieldChangeSet.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->updateRegistration = app(UpdateOrganizationRegistration::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
});

/** The Resubmitted transition this document's last resubmit produced. */
function fcResubmitTransition(Document $document): DocumentTransition
{
    return DocumentTransition::where('document_id', $document->id)
        ->where('action', TransitionAction::Resubmitted->value)
        ->latest('id')
        ->firstOrFail();
}

/** @param  array<int, string>  $flagged */
function fcReturnedRegistration(array $flagged): Document
{
    $engine = app(ApprovalEngine::class);
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();

    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $student->id,
    ]);

    OrganizationRegistrationDetail::factory()->create([
        'document_id' => $doc->id,
        'organization_type' => OrganizationType::CoCurricular,
        'purpose_of_organization' => 'Original purpose.',
        'contact_person' => 'Old Person',
        'contact_no' => '09170000000',
        'email_address' => 'old@nu-lipa.edu.ph',
        'date_organized' => '2020-06-01',
    ]);

    $engine->submit($doc, $student);
    $doc->refresh();
    $engine->returnForRevision($doc, $sdaoA, 'Please revise.', $flagged);
    $doc->refresh();

    return $doc;
}

// ── (a) exactly the flagged section's fields, nothing else ─────────────────

test('registration resubmit captures old and new values for exactly the flagged fields', function () {
    $doc = fcReturnedRegistration(['contact_information']);

    $this->updateRegistration->execute(
        actor: $this->student,
        document: $doc,
        organizationType: OrganizationType::CoCurricular,
        purposeOfOrganization: 'Original purpose.',
        contactPerson: 'New Person',
        contactNo: '09179999999',
        emailAddress: 'new@nu-lipa.edu.ph',
        dateOrganized: '2020-06-01',
        attachmentFiles: registrationAttachmentFiles(),
    );

    $changes = fcResubmitTransition($doc)->field_changes;

    // Only the flagged section is present — organization_details was never
    // flagged, so it is absent even though nothing stopped us capturing it.
    expect(array_keys($changes))->toBe(['contact_information']);
    expect($changes['contact_information']['label'])->toBe('Contact Information');
    expect($changes['contact_information']['status'])->toBe('changed');

    $rows = collect($changes['contact_information']['fields'])->keyBy('key');

    expect($rows->keys()->sort()->values()->all())
        ->toBe(['contact_no', 'contact_person', 'email_address']);

    expect($rows['contact_person']['old'])->toBe('Old Person');
    expect($rows['contact_person']['new'])->toBe('New Person');
    expect($rows['contact_person']['changed'])->toBeTrue();
    expect($rows['email_address']['old'])->toBe('old@nu-lipa.edu.ph');
    expect($rows['email_address']['new'])->toBe('new@nu-lipa.edu.ph');
});

// ── (b) unchanged-but-flagged reports changed: false ───────────────────────

test('a flagged field the student did not touch is recorded with changed false', function () {
    $doc = fcReturnedRegistration(['contact_information', 'organization_details']);

    $this->updateRegistration->execute(
        actor: $this->student,
        document: $doc,
        // organization_details resubmitted BYTE-IDENTICAL, including the enum
        // and the date — the two values that would false-positive if the
        // after-side were built from the raw request payload rather than a
        // re-read through the same casts.
        organizationType: OrganizationType::CoCurricular,
        purposeOfOrganization: 'Original purpose.',
        contactPerson: 'New Person',
        contactNo: '09170000000',
        emailAddress: 'old@nu-lipa.edu.ph',
        dateOrganized: '2020-06-01',
        attachmentFiles: registrationAttachmentFiles(),
    );

    $changes = fcResubmitTransition($doc)->field_changes;
    $details = collect($changes['organization_details']['fields'])->keyBy('key');

    expect($details['organization_type']['changed'])->toBeFalse();
    expect($details['organization_type']['old'])->toBe(OrganizationType::CoCurricular->label());
    expect($details['organization_type']['old'])->toBe($details['organization_type']['new']);
    expect($details['date_organized']['changed'])->toBeFalse();
    expect($details['purpose_of_organization']['changed'])->toBeFalse();

    // The contact section did change — proving the false above is a real
    // comparison, not a broken diff that reports everything unchanged.
    $contact = collect($changes['contact_information']['fields'])->keyBy('key');
    expect($contact['contact_person']['changed'])->toBeTrue();
    expect($contact['contact_no']['changed'])->toBeFalse();
});

// ── (c) proposal: CalendarActivity + ActivityProposal merged ───────────────

test('off-calendar proposal resubmit merges CalendarActivity and ActivityProposal fields', function () {
    $startDraft = app(StartProposalDraft::class);
    $submitProposal = app(SubmitActivityProposal::class);
    $resubmit = app(ResubmitActivityProposal::class);

    $draft = $startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'title' => 'Coding Night',
            'venue' => 'Function Hall',
            'activity_date' => '2026-12-10',
            'start_time' => '09:00',
            'end_time' => '11:00',
        ],
    );

    ['document' => $doc] = $submitProposal->execute(
        actor: $this->student,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    // Off-calendar: SDAO is step 1 (invariant #8).
    $this->engine->returnForRevision(
        $doc,
        $this->sdaoA,
        'Fix the venue and the budget.',
        ['schedule_venue', 'budget'],
    );
    $doc->refresh();

    $resubmit->execute($this->student, $doc, [
        'objectives' => 'Objectives',
        'narrative' => 'Narrative',
        'criteria_mechanics' => 'Criteria',
        'program_flow' => 'Program flow',
        'source_of_funding' => 'Org funds',
        'expense_items' => [['label' => 'Venue', 'amount' => '5000']],
        'proposed_budget' => '5000',
        'title' => 'Coding Night',
        'venue' => 'Main Gymnasium',
        'activity_date' => '2026-12-10',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ]);

    $changes = fcResubmitTransition($doc)->field_changes;

    expect(array_keys($changes))->toContain('schedule_venue', 'budget');

    // schedule_venue's fields live on CalendarActivity, not ActivityProposal.
    $schedule = collect($changes['schedule_venue']['fields'])->keyBy('key');
    expect($schedule['venue']['old'])->toBe('Function Hall');
    expect($schedule['venue']['new'])->toBe('Main Gymnasium');
    expect($schedule['venue']['changed'])->toBeTrue();
    // start_time round-trips through the H:i accessor on both sides.
    expect($schedule['start_time']['old'])->toBe('09:00');
    expect($schedule['start_time']['changed'])->toBeFalse();

    // budget's fields live on ActivityProposal, in the same payload.
    $budget = collect($changes['budget']['fields'])->keyBy('key');
    expect($budget['source_of_funding']['new'])->toBe('Org funds');
    expect($budget['expense_items']['new'])->toBe('Venue: ₱5,000.00');
    expect($budget['proposed_budget']['new'])->toBe('₱5,000.00');
});

test('schedule_venue is skipped for an on-calendar proposal, whose date and venue are uneditable', function () {
    $startDraft = app(StartProposalDraft::class);
    $submitProposal = app(SubmitActivityProposal::class);
    $resubmit = app(ResubmitActivityProposal::class);

    $activity = returnTestApprovedActivity($this->org);

    $draft = $startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );

    ['document' => $doc] = $submitProposal->execute(
        actor: $this->student,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    // On-calendar starts at the adviser step; return from there.
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->engine->returnForRevision($doc, $adviser, 'Revise.', ['schedule_venue', 'objectives']);
    $doc->refresh();

    $resubmit->execute($this->student, $doc, [
        'objectives' => 'Sharper objectives',
        'narrative' => 'Narrative',
        'criteria_mechanics' => 'Criteria',
        'program_flow' => 'Program flow',
        'source_of_funding' => 'Org funds',
        'expense_items' => [],
    ]);

    $changes = fcResubmitTransition($doc)->field_changes;

    // Reporting "no changes" about a field the student physically cannot
    // edit would be misleading, so the section is dropped outright.
    expect($changes)->not->toHaveKey('schedule_venue');
    expect($changes)->toHaveKey('objectives');
    expect($changes['objectives']['fields'][0]['old'])->toBe('Objectives');
    expect($changes['objectives']['fields'][0]['new'])->toBe('Sharper objectives');
});

// ── (d) calendar positional zip, including a count mismatch ────────────────

test('calendar resubmit produces a positional diff per flagged activity row', function () {
    $submit = app(SubmitActivityCalendar::class);
    $update = app(UpdateActivityCalendar::class);

    ['document' => $doc] = $submit->execute(
        actor: $this->student,
        organization: $this->org,
        activities: [
            ['name' => 'Orientation', 'venue' => 'Gymnasium', 'activity_date' => '2026-09-15', 'start_time' => '09:00', 'end_time' => '12:00'],
            ['name' => 'Hackathon', 'venue' => 'AVR 1', 'activity_date' => '2026-10-01', 'start_time' => '08:00', 'end_time' => '17:00'],
        ],
    );

    $this->engine->returnForRevision($doc, $this->sdaoA, 'Fix both.', ['activity_0', 'activity_1']);
    $doc->refresh();

    $update->execute(
        actor: $this->student,
        document: $doc,
        activities: [
            // Row 0: venue changed, everything else identical.
            ['name' => 'Orientation', 'venue' => 'Auditorium', 'activity_date' => '2026-09-15', 'start_time' => '09:00', 'end_time' => '12:00'],
            // Row 1: unchanged.
            ['name' => 'Hackathon', 'venue' => 'AVR 1', 'activity_date' => '2026-10-01', 'start_time' => '08:00', 'end_time' => '17:00'],
        ],
    );

    $changes = fcResubmitTransition($doc)->field_changes;

    expect(array_keys($changes))->toBe(['activity_0', 'activity_1']);
    // Labels are baked in server-side; the frontend needs no lookup.
    expect($changes['activity_0']['label'])->toBe('Activity 1');
    expect($changes['activity_0']['status'])->toBe('changed');

    $row0 = collect($changes['activity_0']['fields'])->keyBy('key');
    expect($row0['venue']['old'])->toBe('Gymnasium');
    expect($row0['venue']['new'])->toBe('Auditorium');
    expect($row0['venue']['changed'])->toBeTrue();
    expect($row0['name']['changed'])->toBeFalse();

    // A flagged section where nothing changed still appears, with every row
    // marked unchanged — that is the "student ignored the flag" signal.
    expect(collect($changes['activity_1']['fields'])->pluck('changed')->unique()->all())
        ->toBe([false]);
});

test('calendar resubmit that deletes a flagged row records it as removed instead of crashing', function () {
    $submit = app(SubmitActivityCalendar::class);
    $update = app(UpdateActivityCalendar::class);

    ['document' => $doc] = $submit->execute(
        actor: $this->student,
        organization: $this->org,
        activities: [
            ['name' => 'Orientation', 'venue' => 'Gymnasium', 'activity_date' => '2026-09-15', 'start_time' => '09:00', 'end_time' => '12:00'],
            ['name' => 'Hackathon', 'venue' => 'AVR 1', 'activity_date' => '2026-10-01', 'start_time' => '08:00', 'end_time' => '17:00'],
        ],
    );

    $this->engine->returnForRevision($doc, $this->sdaoA, 'Drop the second one.', ['activity_1']);
    $doc->refresh();

    // The student deletes the flagged row: index 1 no longer exists after.
    $update->execute(
        actor: $this->student,
        document: $doc,
        activities: [
            ['name' => 'Orientation', 'venue' => 'Gymnasium', 'activity_date' => '2026-09-15', 'start_time' => '09:00', 'end_time' => '12:00'],
        ],
    );

    $changes = fcResubmitTransition($doc)->field_changes;

    expect($changes['activity_1']['status'])->toBe('removed');

    $row = collect($changes['activity_1']['fields'])->keyBy('key');
    expect($row['name']['old'])->toBe('Hackathon');
    expect($row['name']['new'])->toBeNull();
    expect($row['name']['changed'])->toBeTrue();
});

// ── (e) nothing flagged → null ─────────────────────────────────────────────

test('field_changes is null when the return carried no flags', function () {
    $doc = fcReturnedRegistration([]);

    $this->updateRegistration->execute(
        actor: $this->student,
        document: $doc,
        organizationType: OrganizationType::ExtraCurricular,
        purposeOfOrganization: 'Completely rewritten.',
        contactPerson: 'New Person',
        contactNo: '09179999999',
        emailAddress: 'new@nu-lipa.edu.ph',
        dateOrganized: '2021-01-01',
        attachmentFiles: registrationAttachmentFiles(),
    );

    expect(fcResubmitTransition($doc)->field_changes)->toBeNull();
});

test('field_changes is null when only truly field-less sections were flagged', function () {
    // 'general' has no fields by definition and is not an attachment slot,
    // so it is skipped entirely — unlike 'by_laws' (see the attachment
    // marker tests below), it never contributes anything to report.
    $doc = fcReturnedRegistration(['general']);

    $this->updateRegistration->execute(
        actor: $this->student,
        document: $doc,
        organizationType: OrganizationType::ExtraCurricular,
        purposeOfOrganization: 'Rewritten.',
        contactPerson: 'New Person',
        contactNo: '09179999999',
        emailAddress: 'new@nu-lipa.edu.ph',
        dateOrganized: '2021-01-01',
        attachmentFiles: registrationAttachmentFiles(),
    );

    expect(fcResubmitTransition($doc)->field_changes)->toBeNull();
});

// ── (g) a flagged attachment slot gets a status marker, not a field diff ───

test('a flagged attachment slot with no prior file is recorded as added', function () {
    // fcReturnedRegistration() never stores any attachment before returning
    // the document, so by_laws has no prior file — the student's first
    // upload for it on resubmit is "added", not "replaced".
    $doc = fcReturnedRegistration(['by_laws']);

    $this->updateRegistration->execute(
        actor: $this->student,
        document: $doc,
        organizationType: OrganizationType::CoCurricular,
        purposeOfOrganization: 'Original purpose.',
        contactPerson: 'Old Person',
        contactNo: '09170000000',
        emailAddress: 'old@nu-lipa.edu.ph',
        dateOrganized: '2020-06-01',
        attachmentFiles: registrationAttachmentFiles(),
    );

    $changes = fcResubmitTransition($doc)->field_changes;

    expect($changes)->toBe([
        'by_laws' => ['label' => 'By-Laws', 'status' => 'added', 'fields' => []],
    ]);
});

test('a flagged attachment slot with a prior file is recorded as replaced when a new one is uploaded', function () {
    $doc = fcReturnedRegistration(['by_laws']);
    app(AttachmentStorage::class)->store(
        $doc,
        'by_laws',
        UploadedFile::fake()->create('old-by-laws.pdf', 100, 'application/pdf'),
        $this->student,
        multiple: false,
    );

    $this->updateRegistration->execute(
        actor: $this->student,
        document: $doc,
        organizationType: OrganizationType::CoCurricular,
        purposeOfOrganization: 'Original purpose.',
        contactPerson: 'Old Person',
        contactNo: '09170000000',
        emailAddress: 'old@nu-lipa.edu.ph',
        dateOrganized: '2020-06-01',
        attachmentFiles: registrationAttachmentFiles(),
    );

    $changes = fcResubmitTransition($doc)->field_changes;

    expect($changes['by_laws']['status'])->toBe('replaced');
});

test('a flagged attachment slot the student never touched is recorded as unchanged', function () {
    $doc = fcReturnedRegistration(['by_laws']);
    app(AttachmentStorage::class)->store(
        $doc,
        'by_laws',
        UploadedFile::fake()->create('existing-by-laws.pdf', 100, 'application/pdf'),
        $this->student,
        multiple: false,
    );

    $filesWithoutByLaws = registrationAttachmentFiles();
    unset($filesWithoutByLaws['by_laws']);

    $this->updateRegistration->execute(
        actor: $this->student,
        document: $doc,
        organizationType: OrganizationType::CoCurricular,
        purposeOfOrganization: 'Original purpose.',
        contactPerson: 'Old Person',
        contactNo: '09170000000',
        emailAddress: 'old@nu-lipa.edu.ph',
        dateOrganized: '2020-06-01',
        attachmentFiles: $filesWithoutByLaws,
    );

    $changes = fcResubmitTransition($doc)->field_changes;

    expect($changes['by_laws']['status'])->toBe('unchanged');
});

test('a flagged multi-file attachment slot with new uploads is recorded as added, never replaced', function () {
    // AfterActivityReport's 'photos' slot accumulates (multiple: true) — an
    // existing photo is never removed by a new upload, so "replaced" would
    // misdescribe what actually happened. Built directly via factories, the
    // same shortcut fcReturnedRegistration() takes, rather than the full
    // proposal-approval chain SubmitAfterActivityReport's real callers go
    // through — the hard link to a real ActivityProposal row is all the
    // attachment marker itself needs.
    $update = app(UpdateAfterActivityReport::class);

    $proposalDoc = Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Approved,
        'submitted_by' => $this->student->id,
    ]);
    $proposal = ActivityProposal::factory()->create(['document_id' => $proposalDoc->id]);

    $reportDoc = Document::factory()->create([
        'form_type' => FormType::AfterActivityReport,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $this->student->id,
    ]);
    AfterActivityReport::factory()->create([
        'document_id' => $reportDoc->id,
        'activity_proposal_id' => $proposal->id,
        'summary' => 'Summary',
    ]);

    // assertRequiredSlotsFilled() (run by every resubmit, regardless of what
    // was flagged) needs every required slot filled — pre-store the other
    // two so only 'photos' is actually exercised by this test.
    $storage = app(AttachmentStorage::class);
    $storage->store($reportDoc, 'evaluation_form', UploadedFile::fake()->create('eval.pdf', 100, 'application/pdf'), $this->student, multiple: false);
    $storage->store($reportDoc, 'attendance_sheet', UploadedFile::fake()->create('attendance.pdf', 100, 'application/pdf'), $this->student, multiple: false);

    $this->engine->submit($reportDoc, $this->student);
    $reportDoc->refresh();
    $this->engine->returnForRevision($reportDoc, $this->sdaoA, 'Add more photos.', ['photos']);
    $reportDoc->refresh();

    $update->execute(
        actor: $this->student,
        document: $reportDoc,
        summary: 'Summary',
        attachmentFiles: ['photos' => [UploadedFile::fake()->create('extra.jpg', 200, 'image/jpeg')]],
    );

    $changes = fcResubmitTransition($reportDoc)->field_changes;

    expect($changes['photos']['status'])->toBe('added');
});

test('every non-resubmit transition leaves field_changes null', function () {
    $doc = fcReturnedRegistration(['contact_information']);

    expect(
        DocumentTransition::where('document_id', $doc->id)
            ->where('action', '!=', TransitionAction::Resubmitted->value)
            ->get()
            ->pluck('field_changes')
            ->filter()
    )->toBeEmpty();
});

// ── (f) HTTP: the review show() page ships the right shape ─────────────────

test('HTTP: the review show page exposes field_changes on the resubmit history entry', function () {
    $doc = fcReturnedRegistration(['contact_information']);

    $this->updateRegistration->execute(
        actor: $this->student,
        document: $doc,
        organizationType: OrganizationType::CoCurricular,
        purposeOfOrganization: 'Original purpose.',
        contactPerson: 'New Person',
        contactNo: '09170000000',
        emailAddress: 'old@nu-lipa.edu.ph',
        dateOrganized: '2020-06-01',
        attachmentFiles: registrationAttachmentFiles(),
    );

    // Submitted, Returned, Resubmitted → the resubmit is history index 2.
    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route('review.registrations.show', $doc))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('review/registrations/show')
            ->has('history', 3)
            ->where('history.1.action', 'returned')
            ->where('history.1.field_changes', null)
            ->where('history.2.action', 'resubmitted')
            ->where('history.2.field_changes.contact_information.label', 'Contact Information')
            ->where('history.2.field_changes.contact_information.status', 'changed')
            ->where('history.2.field_changes.contact_information.fields.0.key', 'contact_person')
            ->where('history.2.field_changes.contact_information.fields.0.old', 'Old Person')
            ->where('history.2.field_changes.contact_information.fields.0.new', 'New Person')
            ->where('history.2.field_changes.contact_information.fields.0.changed', true)
            ->etc()
        );
});
