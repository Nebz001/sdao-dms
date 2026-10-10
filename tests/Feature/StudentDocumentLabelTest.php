<?php

use App\Dashboard\StudentDocumentLabel;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\Term;
use App\Models\ActivityCalendar;
use App\Models\ActivityProposal;
use App\Models\AfterActivityReport;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use Database\Seeders\WorkflowTemplateSeeder;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * The student-side name of a document is built from its structured fields.
 * The stored `documents.title` ("Activity Proposal — Foo (Org)") is never
 * read, so a stored title full of dashes, the organization and the term still
 * comes out clean.
 */
function labelDocument(FormType $type, Organization $org, string $storedTitle): Document
{
    return Document::factory()->create([
        'form_type' => $type,
        'organization_id' => $org->id,
        'title' => $storedTitle,
        'status' => DocumentStatus::InReview,
    ]);
}

beforeEach(function () {
    $this->org = Organization::factory()->create(['name' => 'PICE']);
});

test('a proposal is titled by its activity, keeps the form type chip, and carries the term of its calendar', function () {
    $document = labelDocument(FormType::ActivityProposal, $this->org, 'Activity Proposal — Civil Engineering Site Visit (PICE)');
    $calendarDocument = labelDocument(FormType::ActivityCalendar, $this->org, 'Activity Calendar — PICE (1st Term 2026-2027)');
    $calendar = ActivityCalendar::factory()->create(['document_id' => $calendarDocument->id, 'academic_year' => '2026-2027', 'term' => Term::FirstTerm]);
    $activity = CalendarActivity::factory()->create(['activity_calendar_id' => $calendar->id]);
    ActivityProposal::factory()->create([
        'document_id' => $document->id,
        'title' => 'Civil Engineering Site Visit',
        'calendar_activity_id' => $activity->id,
    ]);

    expect(StudentDocumentLabel::payload($document->fresh()))->toBe([
        'title' => 'Civil Engineering Site Visit',
        'showFormType' => true,
        'period' => '1st Term, 2026-2027',
    ]);
});

test('a report is titled by its activity, keeps the chip and has no period badge', function () {
    $proposalDocument = labelDocument(FormType::ActivityProposal, $this->org, 'Activity Proposal — Infrastructure Career Fair (PICE)');
    $proposal = ActivityProposal::factory()->create(['document_id' => $proposalDocument->id, 'title' => 'Infrastructure Career Fair']);
    $reportDocument = labelDocument(FormType::AfterActivityReport, $this->org, 'After-Activity Report — Infrastructure Career Fair');
    AfterActivityReport::factory()->create(['document_id' => $reportDocument->id, 'activity_proposal_id' => $proposal->id]);

    expect(StudentDocumentLabel::payload($reportDocument->fresh()))->toBe([
        'title' => 'Infrastructure Career Fair',
        'showFormType' => true,
        'period' => null,
    ]);
});

test('an activity calendar is titled by its form type, drops the chip, and shows term and year as the badge', function () {
    $document = labelDocument(FormType::ActivityCalendar, $this->org, 'Activity Calendar — PICE (1st Term 2026-2027)');
    ActivityCalendar::factory()->create(['document_id' => $document->id, 'academic_year' => '2026-2027', 'term' => Term::FirstTerm]);

    expect(StudentDocumentLabel::payload($document->fresh()))->toBe([
        'title' => 'Activity Calendar',
        'showFormType' => false,
        'period' => '1st Term, 2026-2027',
    ]);
});

test('a registration and a renewal are titled by their form type and badged with the year they are FOR', function () {
    $registration = labelDocument(FormType::OrganizationRegistration, $this->org, 'Organization Registration — PICE (2026-2027)');
    OrganizationRegistrationDetail::factory()->create(['document_id' => $registration->id, 'covers_academic_year' => '2026-2027']);
    $renewal = labelDocument(FormType::OrganizationRenewal, $this->org, 'Organization Renewal — PICE (2027-2028)');
    OrganizationRegistrationDetail::factory()->create(['document_id' => $renewal->id, 'covers_academic_year' => '2027-2028']);

    expect(StudentDocumentLabel::payload($registration->fresh()))->toBe([
        'title' => 'Organization Registration',
        'showFormType' => false,
        'period' => '2026-2027',
    ]);
    expect(StudentDocumentLabel::payload($renewal->fresh()))->toBe([
        'title' => 'Organization Renewal',
        'showFormType' => false,
        'period' => '2027-2028',
    ]);
});

test('no title ever carries the organization name or a dash, whatever was stored', function () {
    $stored = [
        FormType::ActivityProposal->value => 'Activity Proposal — Foo (PICE)',
        FormType::ActivityCalendar->value => 'Activity Calendar — PICE (1st Term 2026-2027)',
        FormType::OrganizationRenewal->value => 'Organization Renewal — PICE (2027-2028)',
        FormType::OrganizationRegistration->value => 'Organization Registration — PICE (2026-2027)',
    ];

    foreach ($stored as $type => $title) {
        $document = labelDocument(FormType::from($type), $this->org, $title);
        $label = StudentDocumentLabel::title($document->fresh());

        expect($label)->not->toContain('PICE')->not->toContain('—')->not->toContain(' - ');
    }
});

test('a proposal with no activity row falls back to its form type, never the stored title', function () {
    $document = labelDocument(FormType::ActivityProposal, $this->org, 'Activity Proposal — Lost (PICE)');

    expect(StudentDocumentLabel::title($document->fresh()))->toBe('Activity Proposal');
});

describe('student lists use it', function () {
    beforeEach(function () {
        $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
        $this->withoutVite();
        $this->student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
        $this->csOrg = Organization::where('name', 'Computing Society')->firstOrFail();
    });

    test('the renewals list and document history send the clean title, chip flag and period', function () {
        $renewal = labelDocument(FormType::OrganizationRenewal, $this->csOrg, 'Organization Renewal — Computing Society (2027-2028)');
        OrganizationRegistrationDetail::factory()->create(['document_id' => $renewal->id, 'covers_academic_year' => '2027-2028']);

        $this->actingAs($this->student)->get(route('renewals.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('renewals.0.title', 'Organization Renewal')
            ->where('renewals.0.showFormType', false)
            ->where('renewals.0.period', '2027-2028'));

        $this->actingAs($this->student)->get(route('document-history.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('documents.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['title'] === 'Organization Renewal'
                && $row['showFormType'] === false
                && $row['period'] === '2027-2028')));
    });

    test('the proposals list sends only the activity name as the title', function () {
        $document = labelDocument(FormType::ActivityProposal, $this->csOrg, 'Activity Proposal — Hack Night (Computing Society)');
        ActivityProposal::factory()->create(['document_id' => $document->id, 'title' => 'Hack Night']);

        $this->actingAs($this->student)->get(route('activity-proposals.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('proposals.0.title', 'Hack Night')
            ->where('proposals.0.showFormType', true));
    });
});
