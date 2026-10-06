<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Dashboard\AdminAttentionData;
use App\Dashboard\DocumentDisplayTitle;
use App\Dashboard\InReviewSnapshot;
use App\Dashboard\ReturnAnalytics;
use App\Dashboard\WeeklySubmissions;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Enums\ProposalCalendarMode;
use App\Enums\Role;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Support\AcademicPeriod;
use App\Support\AcademicYear;
use App\Support\CurrentPeriod;
use Carbon\Carbon;
use Database\Seeders\WorkflowTemplateSeeder;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
});

function attentionRegistration(Organization $org, ApprovalEngine $engine, User $submitter): Document
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

    return $doc->refresh();
}

/** A row on an Approved calendar, `$daysAhead` days from today. */
function attentionCalendarActivity(Organization $org, int $daysAhead, string $name, DocumentStatus $status = DocumentStatus::Approved): CalendarActivity
{
    $doc = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Calendar',
        'status' => $status,
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);
    $calendar = ActivityCalendar::create([
        'document_id' => $doc->id,
        'academic_year' => AcademicYear::current(),
        'term' => 'first_term',
    ]);

    return CalendarActivity::create([
        'activity_calendar_id' => $calendar->id,
        'name' => $name,
        'venue' => 'Auditorium',
        'activity_date' => today()->addDays($daysAhead)->toDateString(),
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);
}

/** An on-calendar proposal in review at the Adviser step. */
function attentionProposalInReview(Organization $org, User $student, CalendarActivity $activity): Document
{
    $draft = app(StartProposalDraft::class)->execute(
        actor: $student,
        organization: $org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    app(SubmitActivityProposal::class)->execute(
        actor: $student,
        document: $draft,
        objectives: "Overall Goal\n\nSpecific Objectives",
        criteriaMechanics: 'Criteria',
        programFlow: 'Flow',
        expenseItems: [['material' => 'Expenses', 'quantity' => '1', 'unit_price' => '100.00']],
    );

    return $draft->refresh();
}

test('idle tiers are fresh under 3 days, aging from 3 to 7, stale over 7', function (int $days, string $tier) {
    expect(InReviewSnapshot::tierFor($days))->toBe($tier);
})->with([
    'zero days' => [0, 'fresh'],
    'two days' => [2, 'fresh'],
    'three days' => [3, 'aging'],
    'seven days' => [7, 'aging'],
    'eight days' => [8, 'stale'],
]);

test('median is the middle value, rounding an even pair', function () {
    expect(InReviewSnapshot::median([]))->toBe(0)
        ->and(InReviewSnapshot::median([4]))->toBe(4)
        ->and(InReviewSnapshot::median([1, 9, 3]))->toBe(3)
        ->and(InReviewSnapshot::median([2, 4, 6, 10]))->toBe(5);
});

test('the SDAO step is one group and its documents idle from the latest transition', function () {
    $first = attentionRegistration($this->org, $this->engine, $this->studentAlpha);
    $second = attentionRegistration($this->itGuild, $this->engine, $this->studentAlpha);
    $first->transitions()->update(['created_at' => now()->subDays(10)]);
    $second->transitions()->update(['created_at' => now()->subDays(2)]);

    $groups = app(InReviewSnapshot::class)->groups();

    expect($groups)->toHaveCount(1)
        ->and($groups->first())->toMatchArray([
            'key' => 'sdao',
            'name' => 'SDAO Office',
            'line' => 'SDAO review step',
            'waiting' => 2,
            'oldest' => 10,
            'median' => 6,
            'tier' => 'stale',
        ]);
});

test('a partial SDAO approval resets the idle clock because it writes a transition', function () {
    $doc = attentionRegistration($this->org, $this->engine, $this->studentAlpha);
    $doc->transitions()->update(['created_at' => now()->subDays(10)]);
    $doc->update(['updated_at' => now()->subDays(10)]);

    $this->engine->approve($doc, $this->sdaoA);

    expect(app(InReviewSnapshot::class)->rows()->first()->idleDays)->toBe(0);
});

test('a proposal at the adviser step groups under the resolved adviser with the org as scope', function () {
    $proposal = attentionProposalInReview($this->org, $this->studentAlpha, attentionCalendarActivity($this->org, 20, 'Hack Day'));

    $row = app(InReviewSnapshot::class)->rows()->firstWhere(fn ($r) => $r->document->is($proposal));
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    expect($row->approverKey)->toBe('user:'.$adviser->id)
        ->and($row->approverName)->toBe($adviser->name)
        ->and($row->approverLine)->toBe('Adviser, Computing Society');
});

test('a vacant seat is grouped as Unassigned and still counted', function () {
    $proposal = attentionProposalInReview($this->org, $this->studentAlpha, attentionCalendarActivity($this->org, 20, 'Hack Day'));
    RoleAssignment::where('role', Role::Adviser->value)->where('organization_id', $this->org->id)->delete();

    $row = app(InReviewSnapshot::class)->rows()->firstWhere(fn ($r) => $r->document->is($proposal));

    expect($row->approverName)->toBe('Unassigned')
        ->and($row->approverKey)->toStartWith('unassigned:adviser:');
});

test('per-approver counts add up to the waiting-on-an-approver number and the stuck tile', function () {
    attentionRegistration($this->org, $this->engine, $this->studentAlpha);
    attentionRegistration($this->itGuild, $this->engine, $this->studentAlpha);
    attentionProposalInReview($this->org, $this->studentAlpha, attentionCalendarActivity($this->org, 20, 'Hack Day'));

    $attention = app(AdminAttentionData::class);
    $tiles = collect($attention->tiles())->keyBy('key');
    $grouped = $attention->stuckByApprover(100);

    expect($grouped['rows'])->toHaveCount(2)
        ->and(collect($grouped['rows'])->sum('waiting'))->toBe(3)
        ->and($attention->waitingSplit()['approver']['count'])->toBe(3)
        ->and($tiles['stuck_with_approvers']['count'])->toBe(3)
        ->and($tiles['awaiting_sdao']['count'])->toBe(2);
});

test('returned documents are their own bucket and never appear in oldest in review', function () {
    $inReview = attentionRegistration($this->org, $this->engine, $this->studentAlpha);
    $returned = attentionRegistration($this->itGuild, $this->engine, $this->studentAlpha);
    $this->engine->returnForRevision($returned, $this->sdaoA, 'Fix it', ['organization_details']);

    $attention = app(AdminAttentionData::class);
    $tiles = collect($attention->tiles())->keyBy('key');

    expect($tiles['returned']['count'])->toBe(1)
        ->and($tiles['returned']['hint'])->toBe('Waiting on 1 organization')
        ->and($attention->waitingSplit())->toMatchArray(['total' => 2])
        ->and($attention->waitingSplit()['org']['count'])->toBe(1)
        ->and($attention->waitingSplit()['approver']['count'])->toBe(1)
        ->and(collect($attention->oldestInReview())->pluck('id')->all())->toBe([$inReview->id]);
});

test('oldest in review is capped at five and ordered oldest first', function () {
    foreach (range(1, 7) as $days) {
        $doc = attentionRegistration($this->org, $this->engine, $this->studentAlpha);
        $doc->transitions()->update(['created_at' => now()->subDays($days)]);
    }

    $idle = collect(app(AdminAttentionData::class)->oldestInReview())->pluck('idleDays')->all();

    expect($idle)->toBe([7, 6, 5, 4, 3]);
});

test('the awaiting SDAO hint counts documents waiting over five days', function () {
    $old = attentionRegistration($this->org, $this->engine, $this->studentAlpha);
    $old->transitions()->update(['created_at' => now()->subDays(6)]);
    attentionRegistration($this->itGuild, $this->engine, $this->studentAlpha);

    $tile = collect(app(AdminAttentionData::class)->tiles())->firstWhere('key', 'awaiting_sdao');

    expect($tile['count'])->toBe(2)
        ->and($tile['hint'])->toBe('1 waiting over 5 days')
        ->and($tile['hintTone'])->toBe('warning');
});

test('the stuck tile hint reports the oldest idle days in plural form', function () {
    $doc = attentionRegistration($this->org, $this->engine, $this->studentAlpha);
    $doc->transitions()->update(['created_at' => now()->subDays(11)]);

    $tile = collect(app(AdminAttentionData::class)->tiles())->firstWhere('key', 'stuck_with_approvers');

    expect($tile['hint'])->toBe('Oldest idle 11 days')
        ->and($tile['hintTone'])->toBe('destructive');
});

test('only approved organizations without an adviser count, and names show when there are three or fewer', function () {
    // Pending registration: never approved, so no adviser gap.
    attentionRegistration($this->org, $this->engine, $this->studentAlpha);

    $approved = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->itGuild->id,
        'status' => DocumentStatus::Approved,
    ]);
    RoleAssignment::where('role', Role::Adviser->value)->where('organization_id', $this->itGuild->id)->delete();

    $attention = app(AdminAttentionData::class);
    $tile = collect($attention->tiles())->firstWhere('key', 'without_adviser');

    expect($approved->exists)->toBeTrue()
        ->and($tile['count'])->toBe(1)
        ->and($tile['hint'])->toBe('IT Guild');
});

test('the upcoming alert lists approved-calendar activities in the next seven days with no approved proposal', function () {
    attentionCalendarActivity($this->org, 3, 'General Assembly');
    attentionCalendarActivity($this->org, 8, 'Too Far Away');
    attentionCalendarActivity($this->org, -1, 'Already Happened');
    attentionCalendarActivity($this->org, 2, 'Tentative Calendar', DocumentStatus::InReview);

    $names = app(AdminAttentionData::class)->upcomingUnapprovedActivities()->pluck('name')->all();

    expect($names)->toBe(['General Assembly']);
});

test('an activity whose proposal is approved leaves the alert', function () {
    $activity = attentionCalendarActivity($this->org, 3, 'Seminar');
    $proposal = attentionProposalInReview($this->org, $this->studentAlpha, $activity);

    expect(app(AdminAttentionData::class)->upcomingUnapprovedActivities())->toHaveCount(1);

    $proposal->update(['status' => DocumentStatus::Approved]);

    expect(app(AdminAttentionData::class)->upcomingUnapprovedActivities())->toHaveCount(0);
});

test('an off-calendar proposal in draft counts but a rejected one does not', function () {
    $draft = app(StartProposalDraft::class)->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'title' => 'Surprise Outreach',
            'venue' => 'Quadrangle',
            'activity_date' => today()->addDays(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
        ],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    expect(app(AdminAttentionData::class)->upcomingUnapprovedActivities()->pluck('name')->all())->toBe(['Surprise Outreach']);

    $draft->update(['status' => DocumentStatus::Rejected]);

    expect(app(AdminAttentionData::class)->upcomingUnapprovedActivities())->toHaveCount(0);
});

test('display titles are built from source models without em dashes or the org baked in', function () {
    $registration = attentionRegistration($this->org, $this->engine, $this->studentAlpha);
    $proposal = attentionProposalInReview($this->org, $this->studentAlpha, attentionCalendarActivity($this->org, 20, 'Hack Day'));

    expect(DocumentDisplayTitle::for($registration->load(DocumentDisplayTitle::relations())))->toBe('Organization Registration: Computing Society')
        ->and(DocumentDisplayTitle::for($proposal->load(DocumentDisplayTitle::relations())))->toBe('Activity Proposal: Hack Day')
        ->and(DocumentDisplayTitle::coverageLabel('2026-2027'))->toBe('2026 to 2027');
});

test('the display title falls back to the stored title with its prefix and org suffix removed', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'organization_id' => $this->org->id,
        'title' => 'Activity Proposal — Orphaned Event (Computing Society)',
    ]);

    expect(DocumentDisplayTitle::for($doc))->toBe('Activity Proposal: Orphaned Event');
});

test('the stuck documents page lists in-review and returned documents and counts match the dashboard', function () {
    attentionRegistration($this->org, $this->engine, $this->studentAlpha);
    $returned = attentionRegistration($this->itGuild, $this->engine, $this->studentAlpha);
    $this->engine->returnForRevision($returned, $this->sdaoA, 'Fix it', ['organization_details']);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.stuck-documents.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/stuck-documents/index')
            ->where('mode', 'documents')
            ->where('documents.meta.total', 2)
            ->where('stats.withApprovers', 1)
            ->where('stats.returned', 1)
        );
});

test('the stuck documents page filters by who the document is waiting on', function () {
    attentionRegistration($this->org, $this->engine, $this->studentAlpha);
    $returned = attentionRegistration($this->itGuild, $this->engine, $this->studentAlpha);
    $this->engine->returnForRevision($returned, $this->sdaoA, 'Fix it', ['organization_details']);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.stuck-documents.index', ['waiting_on' => 'approver']))
        ->assertInertia(fn ($page) => $page
            ->where('documents.meta.total', 1)
            ->where('documents.data.0.state', 'in_review')
        );

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.stuck-documents.index', ['waiting_on' => 'org']))
        ->assertInertia(fn ($page) => $page
            ->where('documents.meta.total', 1)
            ->where('documents.data.0.state', 'returned')
            ->where('documents.data.0.title', 'Organization Registration: IT Guild')
        );
});

test('the stuck documents page filters by approver key, role, idle days and search, ignoring unknown values', function () {
    $old = attentionRegistration($this->org, $this->engine, $this->studentAlpha);
    $old->transitions()->update(['created_at' => now()->subDays(9)]);
    attentionRegistration($this->itGuild, $this->engine, $this->studentAlpha);

    $get = fn (array $query) => $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.stuck-documents.index', $query));

    $get(['approver' => 'sdao'])->assertInertia(fn ($page) => $page->where('documents.meta.total', 2));
    $get(['approver' => 'user:999999'])->assertInertia(fn ($page) => $page->where('documents.meta.total', 0));
    $get(['role' => 'sdao_member'])->assertInertia(fn ($page) => $page->where('documents.meta.total', 2));
    $get(['role' => 'adviser'])->assertInertia(fn ($page) => $page->where('documents.meta.total', 0));
    $get(['idle' => 7])->assertInertia(fn ($page) => $page->where('documents.meta.total', 1));
    $get(['search' => 'computing'])->assertInertia(fn ($page) => $page->where('documents.meta.total', 1));
    $get(['idle' => 4, 'waiting_on' => 'nonsense', 'role' => 'nope'])
        ->assertInertia(fn ($page) => $page->where('documents.meta.total', 2)->where('filters.idle', null));
});

test('the upcoming view lists the activities behind the dashboard alert', function () {
    attentionCalendarActivity($this->org, 3, 'General Assembly');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.stuck-documents.index', ['view' => 'upcoming']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('mode', 'upcoming')
            ->where('activities.meta.total', 1)
            ->where('activities.data.0.name', 'General Assembly')
        );
});

test('only SDAO members can open the stuck documents page', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    $this->actingAs($this->studentAlpha)->withoutVite()->get(route('admin.stuck-documents.index'))->assertForbidden();
    $this->actingAs($adviser)->withoutVite()->get(route('admin.stuck-documents.index'))->assertForbidden();
});

test('the organizations page can filter to approved organizations without an adviser', function () {
    Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->itGuild->id,
        'status' => DocumentStatus::Approved,
    ]);
    RoleAssignment::where('role', Role::Adviser->value)->where('organization_id', $this->itGuild->id)->delete();

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.organizations.index', ['adviser' => 'none']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('organizations.meta.total', 1)
            ->where('organizations.data.0.name', 'IT Guild')
            ->where('filters.adviser', 'none')
        );
});

test('the dashboard sends the alert, tiles, stuck table, split and oldest list', function () {
    attentionRegistration($this->org, $this->engine, $this->studentAlpha);
    attentionCalendarActivity($this->org, 3, 'General Assembly');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('upcomingAlert.count', 1)
            ->where('upcomingAlert.names.0', 'General Assembly')
            ->has('tiles', 5)
            ->where('tiles.0.label', 'Awaiting SDAO review')
            ->where('stuckByApprover.total', 1)
            ->where('waitingSplit.total', 1)
            ->where('oldestInReview.0.title', 'Organization Registration: Computing Society')
        );
});

test('the dashboard has no alert when nothing upcoming is unapproved', function () {
    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertInertia(fn ($page) => $page->where('upcomingAlert', null));
});

test('the proposal funnel counts proposals that cleared each step of their own chain, plus approved', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    attentionProposalInReview($this->org, $this->studentAlpha, attentionCalendarActivity($this->org, 20, 'Stays at adviser'));
    $advanced = attentionProposalInReview($this->org, $this->studentAlpha, attentionCalendarActivity($this->org, 21, 'Moves on'));
    $this->engine->approve($advanced, $adviser);
    $approved = attentionProposalInReview($this->org, $this->studentAlpha, attentionCalendarActivity($this->org, 22, 'Done'));
    $approved->update(['status' => DocumentStatus::Approved]);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('proposalFunnel', 1)
            ->where('proposalFunnel.0.variant', 'regular_on_calendar')
            ->where('proposalFunnel.0.label', 'Regular, On-Calendar')
            ->where('proposalFunnel.0.submitted', 3)
            ->where('proposalFunnel.0.approved', 1)
            // The Adviser step is cleared by the advanced proposal and the
            // approved one; Program Chair only by the approved one.
            ->where('proposalFunnel.0.steps.0', ['label' => 'Adviser', 'count' => 2])
            ->where('proposalFunnel.0.steps.1', ['label' => 'Program Chair', 'count' => 1])
            ->where('proposalFunnel.0.steps.3.label', 'SDAO')
        );
});

test('the proposal funnel only lists variants that have proposals this academic year, built from data not a fixed list', function () {
    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertInertia(fn ($page) => $page->has('proposalFunnel', 0));
});

test('status distribution rows link to the live lists and the current-year archive, never for drafts', function () {
    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertInertia(fn ($page) => $page
            ->where('statusDistribution.0.status', 'draft')
            ->where('statusDistribution.0.href', null)
            ->where('statusDistribution.1.href', route('admin.stuck-documents.index', ['waiting_on' => 'approver']))
            ->where('statusDistribution.2.href', route('admin.stuck-documents.index', ['waiting_on' => 'org']))
            ->where('statusDistribution.3.href', route('admin.archive.index', ['status' => 'approved', 'academic_year' => 'current']))
            ->where('statusDistribution.4.href', route('admin.archive.index', ['status' => 'rejected', 'academic_year' => 'current']))
        );
});

test('the archive can be limited to documents created in the current academic year', function () {
    $current = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Approved,
    ]);
    Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->itGuild->id,
        'status' => DocumentStatus::Approved,
        'created_at' => now()->subYears(3),
    ]);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index', ['academic_year' => 'current']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('documents.data', 1)
            ->where('documents.data.0.id', $current->id)
            ->where('filters.academic_year', 'current')
        );

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index'))
        ->assertInertia(fn ($page) => $page->has('documents.data', 2)->where('filters.academic_year', null));
});

/**
 * A document returned by SDAO with the given flagged sections: submitted,
 * then returned through the real engine so the transition carries the flags.
 *
 * @param  array<int, string>  $flags
 */
function attentionReturnedRegistration(Organization $org, ApprovalEngine $engine, User $submitter, User $sdao, array $flags): Document
{
    $doc = attentionRegistration($org, $engine, $submitter);
    $engine->returnForRevision($doc, $sdao, 'Please fix', $flags);

    return $doc->refresh();
}

/** @return array{reasons: array<string, mixed>, rates: array<string, mixed>} */
function attentionAnalytics(): array
{
    [$start, $end] = CurrentPeriod::get()->academicYearRange();

    return app(ReturnAnalytics::class)->forAcademicYear($start, $end);
}

test('return reasons say not enough data below the minimum sample', function () {
    foreach (range(1, 3) as $i) {
        attentionReturnedRegistration($this->org, $this->engine, $this->studentAlpha, $this->sdaoA, ['organization_details']);
    }

    $reasons = attentionAnalytics()['reasons'];

    expect($reasons['sample'])->toBe(3)
        ->and($reasons['enough'])->toBeFalse()
        ->and($reasons['rows'])->toBe([]);
});

test('return reasons rank sections by the share of flagged returns, labelling attachments by slot', function () {
    foreach (range(1, 6) as $i) {
        attentionReturnedRegistration($this->org, $this->engine, $this->studentAlpha, $this->sdaoA, ['organization_details', 'letter_of_intent']);
    }
    foreach (range(1, 4) as $i) {
        attentionReturnedRegistration($this->org, $this->engine, $this->studentAlpha, $this->sdaoA, ['organization_details']);
    }

    $reasons = attentionAnalytics()['reasons'];

    expect($reasons['sample'])->toBe(10)
        ->and($reasons['enough'])->toBeTrue()
        ->and($reasons['rows'])->toBe([
            ['label' => 'Organization Details', 'count' => 10, 'percent' => 100],
            ['label' => 'Attachment: Letter of Intent', 'count' => 6, 'percent' => 60],
        ]);
});

test('an unknown legacy section key is humanized instead of dropped', function () {
    foreach (range(1, 10) as $i) {
        attentionReturnedRegistration($this->org, $this->engine, $this->studentAlpha, $this->sdaoA, ['organization_details']);
    }
    $legacy = attentionRegistration($this->itGuild, $this->engine, $this->studentAlpha);
    $legacy->transitions()->create([
        'actor_id' => $this->sdaoA->id,
        'action' => 'returned',
        'from_status' => 'in_review',
        'to_status' => 'returned',
        'step_position' => 1,
        'flagged_sections' => ['old_removed_key'],
        'created_at' => now(),
    ]);

    $labels = collect(attentionAnalytics()['reasons']['rows'])->pluck('label');

    expect($labels)->toContain('Old Removed Key');
});

test('return rate counts documents sent back at least once and hides small samples', function () {
    foreach (range(1, 3) as $i) {
        attentionRegistration($this->org, $this->engine, $this->studentAlpha);
    }
    foreach (range(1, 2) as $i) {
        attentionReturnedRegistration($this->org, $this->engine, $this->studentAlpha, $this->sdaoA, ['general']);
    }
    // A document returned twice still counts once.
    $twice = attentionRegistration($this->org, $this->engine, $this->studentAlpha);
    $this->engine->returnForRevision($twice, $this->sdaoA, 'one', ['general']);
    $this->engine->resubmit($twice->refresh(), $this->studentAlpha);
    $this->engine->returnForRevision($twice->refresh(), $this->sdaoA, 'two', ['general']);

    $rows = collect(attentionAnalytics()['rates']['rows'])->keyBy('formType');

    // 6 registrations submitted (3 untouched, 2 returned, 1 returned twice); 3 were returned.
    expect($rows['organization_registration'])->toMatchArray(['submitted' => 6, 'returned' => 3, 'enough' => true, 'percent' => 50])
        ->and($rows['organization_renewal'])->toMatchArray(['submitted' => 0, 'enough' => false, 'percent' => null]);
});

test('drafts are not submissions in the return rate', function () {
    foreach (range(1, 5) as $i) {
        Document::factory()->create([
            'form_type' => FormType::OrganizationRegistration,
            'organization_id' => $this->org->id,
            'status' => DocumentStatus::Draft,
        ]);
    }

    $row = collect(attentionAnalytics()['rates']['rows'])->firstWhere('formType', 'organization_registration');

    expect($row['submitted'])->toBe(0)->and($row['enough'])->toBeFalse();
});

test('average submissions before approval is one plus resubmissions, shown only with enough approved documents', function () {
    $docs = collect(range(1, 5))->map(fn () => attentionRegistration($this->org, $this->engine, $this->studentAlpha));
    $docs->each->update(['status' => DocumentStatus::Approved]);

    expect(attentionAnalytics()['rates']['average'])->toMatchArray(['enough' => true, 'value' => 1.0, 'sample' => 5]);

    // Two of them were resubmitted once each: (3 * 1 + 2 * 2) / 5 = 1.4.
    foreach ($docs->take(2) as $doc) {
        $doc->transitions()->create([
            'actor_id' => $this->studentAlpha->id,
            'action' => 'resubmitted',
            'from_status' => 'returned',
            'to_status' => 'in_review',
            'step_position' => 1,
            'created_at' => now(),
        ]);
    }

    expect(attentionAnalytics()['rates']['average']['value'])->toBe(1.4);
});

test('the average is withheld below five approved documents', function () {
    attentionRegistration($this->org, $this->engine, $this->studentAlpha)->update(['status' => DocumentStatus::Approved]);

    expect(attentionAnalytics()['rates']['average'])->toMatchArray(['enough' => false, 'value' => null]);
});

test('return analytics are scoped to the current academic year', function () {
    foreach (range(1, 10) as $i) {
        $doc = attentionReturnedRegistration($this->org, $this->engine, $this->studentAlpha, $this->sdaoA, ['general']);
        $doc->forceFill(['created_at' => now()->subYears(3)])->save();
    }

    expect(attentionAnalytics()['reasons']['sample'])->toBe(0);
});

test('the dashboard defers return analytics to a second request', function () {
    foreach (range(1, 10) as $i) {
        attentionReturnedRegistration($this->org, $this->engine, $this->studentAlpha, $this->sdaoA, ['general']);
    }

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->missing('returnAnalytics')
            ->loadDeferredProps('analytics', fn ($reload) => $reload
                ->where('returnAnalytics.reasons.sample', 10)
                ->where('returnAnalytics.reasons.rows.0.label', 'General')
                ->where('returnAnalytics.reasons.rows.0.percent', 100)
            )
        );
});

test('term ranges are read off the month to term calendar, half open', function (string $term, string $start, string $end) {
    [$from, $to] = AcademicPeriod::fromString("2026-2027:{$term}")->termRange();

    expect($from->toDateString())->toBe($start)
        ->and($to->toDateString())->toBe($end);
})->with([
    'first term' => ['first_term', '2026-08-01', '2026-12-01'],
    'second term' => ['second_term', '2026-12-01', '2027-04-01'],
    'third term' => ['third_term', '2027-04-01', '2027-08-01'],
]);

/** Writes a transition at an exact moment for a throwaway document. */
function attentionTransitionAt(Organization $org, string $action, string $when): void
{
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $org->id,
        'status' => DocumentStatus::InReview,
    ]);
    DocumentTransition::create([
        'document_id' => $doc->id,
        'actor_id' => null,
        'action' => $action,
        'from_status' => 'draft',
        'to_status' => 'in_review',
        'step_position' => 1,
        'created_at' => $when,
    ]);
}

test('weekly submissions chart the current term from its first week, labelling this week Now', function () {
    $now = Carbon::parse('2026-10-02 10:00:00');
    $period = AcademicPeriod::fromString('2026-2027:first_term');

    foreach (['2026-09-29 09:00', '2026-09-30 09:00', '2026-10-01 09:00'] as $when) {
        attentionTransitionAt($this->org, 'submitted', $when);
    }
    attentionTransitionAt($this->org, 'submitted', '2026-09-22 09:00');
    // A resubmission is not a second submission.
    attentionTransitionAt($this->org, 'resubmitted', '2026-10-01 11:00');

    $data = app(WeeklySubmissions::class)->forPeriod($period, $now);

    expect($data['termLabel'])->toBe('1st Term')
        ->and($data['weeks'])->toHaveCount(10)
        ->and($data['weeks'][0]['label'])->toBe('W1')
        ->and($data['weeks'][8]['label'])->toBe('W9')
        ->and($data['weeks'][9])->toMatchArray(['label' => 'Now', 'current' => true, 'count' => 3, 'start' => '2026-09-28'])
        ->and($data['weeks'][8]['count'])->toBe(1)
        ->and($data['thisWeek'])->toBe(3)
        ->and($data['lastWeek'])->toBe(1)
        ->and($data['delta'])->toBe(2);
});

test('a submission on the Monday boundary belongs to the new week only', function () {
    $now = Carbon::parse('2026-10-02 10:00:00');
    attentionTransitionAt($this->org, 'submitted', '2026-09-28 00:00:00');
    attentionTransitionAt($this->org, 'submitted', '2026-09-27 23:59:59');

    $data = app(WeeklySubmissions::class)->forPeriod(AcademicPeriod::fromString('2026-2027:first_term'), $now);

    expect($data['thisWeek'])->toBe(1)->and($data['lastWeek'])->toBe(1);
});

test('after the term has ended the last week is not labelled Now', function () {
    $now = Carbon::parse('2026-12-10 10:00:00');

    $data = app(WeeklySubmissions::class)->forPeriod(AcademicPeriod::fromString('2026-2027:first_term'), $now);
    $last = collect($data['weeks'])->last();

    expect($last['current'])->toBeFalse()->and($last['label'])->not->toBe('Now');
});

test('a term that has not started yet has no weeks', function () {
    $now = Carbon::parse('2026-10-02 10:00:00');

    $data = app(WeeklySubmissions::class)->forPeriod(AcademicPeriod::fromString('2026-2027:second_term'), $now);

    expect($data['weeks'])->toBe([])->and($data['delta'])->toBeNull();
});

test('the dashboard defers weekly submissions alongside the return analytics', function () {
    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertInertia(fn ($page) => $page
            ->missing('weeklySubmissions')
            ->loadDeferredProps('analytics', fn ($reload) => $reload
                ->has('weeklySubmissions.weeks')
                ->has('returnAnalytics.reasons')
            )
        );
});

test('the activity log can be limited to a date range, inclusive of both days', function () {
    attentionTransitionAt($this->org, 'submitted', '2026-09-01 12:00:00');
    attentionTransitionAt($this->org, 'submitted', '2026-09-05 23:30:00');
    attentionTransitionAt($this->org, 'submitted', '2026-09-09 08:00:00');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index', ['from' => '2026-09-02', 'to' => '2026-09-05']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('transitions.meta.total', 1)
            ->where('filters.from', '2026-09-02')
            ->where('filters.to', '2026-09-05')
        );
});

test('an invalid activity log date is ignored rather than trusted', function () {
    attentionTransitionAt($this->org, 'submitted', '2026-09-01 12:00:00');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index', ['from' => 'not-a-date', 'to' => '2026-13-45', 'date' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('transitions.meta.total', 1)
            ->where('filters.from', null)
            ->where('filters.to', null)
        );
});
