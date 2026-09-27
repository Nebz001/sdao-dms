<?php

use App\ActivityProposals\ProposalReference;
use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\Organization;
use App\Models\User;
use App\Support\AcademicYear;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);

    $this->startDraft = app(StartProposalDraft::class);
    $this->submitProposal = app(SubmitActivityProposal::class);
});

function downloadTestApprovedActivity(Organization $org, string $name): CalendarActivity
{
    $doc = Document::create([
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
        'document_id' => $doc->id,
        'academic_year' => AcademicYear::current(),
        'term' => 'first_term',
    ]);

    return CalendarActivity::create([
        'activity_calendar_id' => $cal->id,
        'name' => $name,
        'venue' => 'Download Test Hall',
        'activity_date' => '2026-11-20',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ]);
}

/**
 * Submits a real on-calendar proposal with real, faked-disk-backed
 * attachments (via proposalStepOneAttachmentFiles()), sitting at step 1
 * (the adviser).
 */
function downloadTestSubmitProposal(object $test, Organization $org, User $student, string $title): Document
{
    $activity = downloadTestApprovedActivity($org, $title);

    $draft = $test->startDraft->execute(
        actor: $student,
        organization: $org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    return $test->submitProposal->execute(
        actor: $student,
        document: $draft,
        objectives: "Overall Goal\n\nSpecific Objectives",
    )['document'];
}

function downloadTestToken(User $user): string
{
    return $user->createToken('Phone')->plainTextToken;
}

test('an approver at the current step downloads the attachment\'s real bytes with the correct content type', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $doc = downloadTestSubmitProposal($this, $org, $student, 'Download Success');

    $attachment = $doc->attachments()->firstOrFail();
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $reference = ProposalReference::format($doc);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.downloadTestToken($adviser)])
        ->get("/api/documents/{$reference}/attachments/{$attachment->id}/download");

    $response->assertOk();
    $response->assertHeader('Content-Type', $attachment->mime_type);
    expect($response->headers->get('Content-Disposition'))->toContain($attachment->original_filename);
    // StreamedResponse::getContent() always returns false (nothing's been
    // "sent" yet) — streamedContent() actually runs the callback and
    // captures its output, the correct way to assert streamed bytes.
    expect($response->streamedContent())->toBe(Storage::disk($attachment->disk)->get($attachment->path));
});

test('404 when the attachment id belongs to a different proposal', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();

    $docA = downloadTestSubmitProposal($this, $org, $student, 'Owner Proposal');
    $docB = downloadTestSubmitProposal($this, $org, $student, 'Other Proposal');

    $attachmentFromB = $docB->attachments()->firstOrFail();
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $referenceA = ProposalReference::format($docA);

    $this->withHeaders(['Authorization' => 'Bearer '.downloadTestToken($adviser)])
        ->getJson("/api/documents/{$referenceA}/attachments/{$attachmentFromB->id}/download")
        ->assertStatus(404)
        ->assertJson(['message' => 'Attachment not found.']);
});

test('403 for an approver whose step has not been reached yet, not the file', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $doc = downloadTestSubmitProposal($this, $org, $student, 'Not Yet Reached'); // sits at step 1 (adviser)

    $attachment = $doc->attachments()->firstOrFail();
    $dean = User::where('email', 'dean-ccit@nu-lipa.edu.ph')->firstOrFail();
    $reference = ProposalReference::format($doc);

    $this->withHeaders(['Authorization' => 'Bearer '.downloadTestToken($dean)])
        ->getJson("/api/documents/{$reference}/attachments/{$attachment->id}/download")
        ->assertStatus(403);
});

test('404 with a clear JSON error when the attachment record exists but the file is missing from storage', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $doc = downloadTestSubmitProposal($this, $org, $student, 'Missing File');

    // The factory's default path is a random UUID that was never actually
    // written to the faked disk — a real DB row, no backing file.
    $orphanAttachment = DocumentAttachment::factory()->for($doc)->create([
        'slot_key' => 'orphan_slot',
        'original_filename' => 'ghost.pdf',
    ]);

    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $reference = ProposalReference::format($doc);

    $this->withHeaders(['Authorization' => 'Bearer '.downloadTestToken($adviser)])
        ->getJson("/api/documents/{$reference}/attachments/{$orphanAttachment->id}/download")
        ->assertStatus(404)
        ->assertJson(['message' => 'The file for this attachment could not be found in storage.']);
});

test('404 for an unknown attachment id on an otherwise valid proposal', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $doc = downloadTestSubmitProposal($this, $org, $student, 'Unknown Attachment Id');

    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $reference = ProposalReference::format($doc);

    $this->withHeaders(['Authorization' => 'Bearer '.downloadTestToken($adviser)])
        ->getJson("/api/documents/{$reference}/attachments/999999/download")
        ->assertStatus(404)
        ->assertJson(['message' => 'Attachment not found.']);
});
