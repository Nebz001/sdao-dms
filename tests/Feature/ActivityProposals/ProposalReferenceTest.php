<?php

use App\ActivityProposals\ProposalReference;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Models\Document;
use App\Models\Organization;
use Illuminate\Support\Carbon;
use Tests\Fixtures\TestIdentitySeeder;

beforeEach(function () {
    $this->seed(TestIdentitySeeder::class);
    $this->org = Organization::first();
});

function makeReferenceProposal(Organization $org, ?Carbon $createdAt = null): Document
{
    $doc = Document::create([
        'form_type' => FormType::ActivityProposal,
        'variant' => 'regular_on_calendar',
        'title' => 'Reference Test Proposal',
        'status' => DocumentStatus::Draft,
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);

    if ($createdAt !== null) {
        $doc->forceFill(['created_at' => $createdAt])->save();
        $doc->refresh();
    }

    return $doc;
}

test('format() produces PROP-{year}-{id zero-padded to at least 3 digits}', function () {
    $doc = makeReferenceProposal($this->org, Carbon::create(2026, 6, 15, 12, 0, 0, 'UTC'));

    expect(ProposalReference::format($doc))->toBe('PROP-2026-'.sprintf('%03d', $doc->id));
});

test('format() uses the Asia/Manila year, not the raw UTC year', function () {
    // 2026-12-31 16:30 UTC is 2027-01-01 00:30 in Asia/Manila (UTC+8).
    $doc = makeReferenceProposal($this->org, Carbon::create(2026, 12, 31, 16, 30, 0, 'UTC'));

    expect(ProposalReference::format($doc))->toBe('PROP-2027-'.sprintf('%03d', $doc->id));
});

test('resolve() round-trips a real proposal back to the same document', function () {
    $doc = makeReferenceProposal($this->org, Carbon::create(2026, 6, 15, 12, 0, 0, 'UTC'));

    $resolved = ProposalReference::resolve(ProposalReference::format($doc));

    expect($resolved)->not->toBeNull();
    expect($resolved->id)->toBe($doc->id);
});

test('resolve() returns null for a malformed reference', function () {
    expect(ProposalReference::resolve('not-a-reference'))->toBeNull();
    expect(ProposalReference::resolve('PROP-26-1'))->toBeNull();
    expect(ProposalReference::resolve('PROP-2026-abc'))->toBeNull();
    expect(ProposalReference::resolve(''))->toBeNull();
});

test('resolve() returns null for the wrong year', function () {
    $doc = makeReferenceProposal($this->org, Carbon::create(2026, 6, 15, 12, 0, 0, 'UTC'));

    expect(ProposalReference::resolve("PROP-2025-{$doc->id}"))->toBeNull();
});

test('resolve() returns null for non-canonical zero-padding', function () {
    $doc = makeReferenceProposal($this->org, Carbon::create(2026, 6, 15, 12, 0, 0, 'UTC'));

    // The canonical form is at least 3 digits — an extra leading zero must
    // not resolve to the same document as a second valid spelling of it.
    expect(ProposalReference::resolve('PROP-2026-0'.$doc->id))->toBeNull();
});

test('resolve() returns null for an unknown id', function () {
    expect(ProposalReference::resolve('PROP-2026-999999'))->toBeNull();
});

test('resolve() returns null for a document that is not an Activity Proposal', function () {
    $calendarDoc = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Not a proposal',
        'status' => DocumentStatus::Draft,
        'current_step_position' => null,
        'organization_id' => $this->org->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);
    $calendarDoc->forceFill(['created_at' => Carbon::create(2026, 6, 15, 12, 0, 0, 'UTC')])->save();
    $calendarDoc->refresh();

    $fakeReference = 'PROP-2026-'.sprintf('%03d', $calendarDoc->id);

    expect(ProposalReference::resolve($fakeReference))->toBeNull();
});
