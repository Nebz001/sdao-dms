<?php

use App\ActivityProposals\RevisionSectionParser;
use App\Approval\SectionFlags;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Models\Document;
use App\Models\Organization;
use Tests\Fixtures\TestIdentitySeeder;

beforeEach(function () {
    $this->seed(TestIdentitySeeder::class);

    $this->document = Document::create([
        'form_type' => FormType::ActivityProposal,
        'variant' => 'regular_on_calendar',
        'title' => 'Parser Test Proposal',
        'status' => DocumentStatus::InReview,
        'current_step_position' => 1,
        'organization_id' => Organization::first()->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);
});

test('parses a straightforward two-label line', function () {
    $remarks = "Please fix the budget and venue.\n\nSections needing revision: Budget, Schedule & Venue";

    expect(RevisionSectionParser::parse($remarks, $this->document))
        ->toEqualCanonicalizing(['budget', 'schedule_venue']);
});

test('the label containing a comma is matched whole and cannot double-count Objectives', function () {
    $remarks = 'Sections needing revision: Request Letter (must include Rationale, Objectives, and Program), Budget';

    $keys = RevisionSectionParser::parse($remarks, $this->document);

    expect($keys)->toContain('request_letter');
    expect($keys)->toContain('budget');
    expect($keys)->not->toContain('objectives');
    expect($keys)->toHaveCount(2);
});

test('a standalone Objectives label is still matched when it really is present', function () {
    $remarks = 'Sections needing revision: Objectives, Budget';

    expect(RevisionSectionParser::parse($remarks, $this->document))
        ->toEqualCanonicalizing(['objectives', 'budget']);
});

test('both the multi-comma label and a genuine standalone Objectives entry are each matched once', function () {
    $remarks = 'Sections needing revision: Request Letter (must include Rationale, Objectives, and Program), Objectives, General';

    $keys = RevisionSectionParser::parse($remarks, $this->document);

    expect($keys)->toEqualCanonicalizing(['request_letter', 'objectives', 'general']);
});

test('unknown labels are ignored for flagging but do not break other matches', function () {
    $remarks = 'Sections needing revision: Budget, Something We Have Never Heard Of, General';

    expect(RevisionSectionParser::parse($remarks, $this->document))
        ->toEqualCanonicalizing(['budget', 'general']);
});

test('remarks with no sections line at all produce no flags', function () {
    $remarks = 'Please just double check everything.';

    expect(RevisionSectionParser::parse($remarks, $this->document))->toBe([]);
});

test('matching is case-insensitive', function () {
    $remarks = 'Sections needing revision: budget, SCHEDULE & VENUE';

    expect(RevisionSectionParser::parse($remarks, $this->document))
        ->toEqualCanonicalizing(['budget', 'schedule_venue']);
});

test('only the LAST sections line in the remarks is used', function () {
    $remarks = "Sections needing revision: Budget\n\nActually scratch that.\n\nSections needing revision: General";

    expect(RevisionSectionParser::parse($remarks, $this->document))
        ->toEqualCanonicalizing(['general']);
});

test('every real section label for Activity Proposal is individually matchable', function () {
    $allLabels = array_map(
        fn ($flag) => $flag->label,
        SectionFlags::for(FormType::ActivityProposal),
    );

    foreach ($allLabels as $label) {
        $remarks = "Sections needing revision: {$label}";
        $keys = RevisionSectionParser::parse($remarks, $this->document);

        expect($keys)->toHaveCount(1, "Label '{$label}' did not match exactly once.");
    }
});
