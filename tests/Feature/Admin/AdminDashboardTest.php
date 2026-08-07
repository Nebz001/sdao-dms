<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Enums\AccountStatus;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Enums\ProposalCalendarMode;
use App\Enums\Role;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Support\AcademicYear;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * The admin dashboard sits behind the same `can:access-admin` gate as the
 * rest of admin/* (AppServiceProvider::configureGates(), Role::SdaoMember
 * only) — mirrors DocumentArchiveAuthorizationTest's cast of non-SDAO roles.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail(); // President, Computing Society
    $this->studentDelta = User::where('email', 'student-delta@sdao.test')->firstOrFail(); // Secretary, Computing Society
    $this->adviserOne = User::where('email', 'adviser-one@sdao.test')->firstOrFail();
    $this->deanCcit = User::where('email', 'dean-ccit@sdao.test')->firstOrFail();
});

function adminDashboardApprovedActivity(Organization $org): CalendarActivity
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
    $calendar = ActivityCalendar::create([
        'document_id' => $doc->id,
        'academic_year' => AcademicYear::current(),
        'term' => 'first_term',
    ]);

    return CalendarActivity::create([
        'activity_calendar_id' => $calendar->id,
        'name' => 'Test Event',
        'venue' => 'Auditorium',
        'activity_date' => '2026-10-15',
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);
}

function inReviewRegistration(Organization $org, ApprovalEngine $engine, User $submitter): Document
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
    $doc->refresh();

    return $doc;
}

test('an SDAO member can open the admin dashboard', function () {
    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertOk();
});

test('a student officer, an adviser, a dean, and a bare account all get 403 on the admin dashboard', function () {
    $this->actingAs($this->studentAlpha)->withoutVite()->get(route('admin.dashboard.index'))->assertForbidden();
    $this->actingAs($this->adviserOne)->withoutVite()->get(route('admin.dashboard.index'))->assertForbidden();
    $this->actingAs($this->deanCcit)->withoutVite()->get(route('admin.dashboard.index'))->assertForbidden();

    $bareUser = User::factory()->create();
    $this->actingAs($bareUser)->withoutVite()->get(route('admin.dashboard.index'))->assertForbidden();
});

test('quick stats reflect pending accounts and unassigned advisers', function () {
    User::factory()->create(['account_status' => AccountStatus::Unverified]);
    User::factory()->create(['account_status' => AccountStatus::Unverified]);

    $unassignedAdviser = User::factory()->create();
    RoleAssignment::create([
        'user_id' => $unassignedAdviser->id,
        'role' => Role::Adviser->value,
        'organization_id' => null,
    ]);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('quickStats.2.label', 'Pending Accounts')
            ->where('quickStats.2.count', 2)
            ->where('quickStats.3.label', 'Unassigned Advisers')
            ->where('quickStats.3.count', 1)
        );
});

test('quick stats count awaiting-review documents across the four short chains', function () {
    inReviewRegistration($this->org, $this->engine, $this->studentAlpha);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('quickStats.0.label', 'Awaiting Your Review')
            ->where('quickStats.0.count', 1)
        );
});

test('status distribution counts every status, including zero counts, scoped to documents created this academic year', function () {
    inReviewRegistration($this->org, $this->engine, $this->studentAlpha);

    Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->itGuild->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => null,
        'created_at' => now()->subYears(3),
    ]);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('statusDistribution', 5)
            ->where('statusDistribution.1.status', 'in_review')
            ->where('statusDistribution.1.count', 1)
            // The 3-year-old draft falls outside the current academic year
            // range and must not be counted.
            ->where('statusDistribution.0.status', 'draft')
            ->where('statusDistribution.0.count', 0)
        );
});

test('the proposal funnel groups an in-review proposal by variant and labels the step by role, not position', function () {
    $activity = adminDashboardApprovedActivity($this->org);

    $draft = app(StartProposalDraft::class)->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );

    app(SubmitActivityProposal::class)->execute(
        actor: $this->studentAlpha,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
        criteriaMechanics: 'Criteria',
        programFlow: 'Flow',
        sourceOfFunding: 'Funding',
        expenseItems: [['label' => 'Expenses', 'amount' => '100.00']],
    );

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('proposalFunnel', 1)
            ->where('proposalFunnel.0.variant', 'regular_on_calendar')
            ->where('proposalFunnel.0.total', 1)
            // Step 1 of the on-calendar chain is the Adviser (CLAUDE.md #8)
            // — the label must say "Adviser", never a raw step number.
            ->where('proposalFunnel.0.steps.0.role', 'Adviser')
            ->where('proposalFunnel.0.steps.0.count', 1)
        );
});

test('recent activity lists a submitted transition with the actor and document', function () {
    $doc = inReviewRegistration($this->org, $this->engine, $this->studentAlpha);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('recentActivity', 1)
            ->where('recentActivity.0.actorName', $this->studentAlpha->name)
            ->where('recentActivity.0.action', 'submitted')
            ->where('recentActivity.0.documentTitle', $doc->title)
            ->where('recentActivity.0.organizationName', 'Computing Society')
        );
});

test('oldest in-review uses the latest transition as the activity clock, not documents.updated_at', function () {
    $doc = inReviewRegistration($this->org, $this->engine, $this->studentAlpha);
    // Backdate both the document row and its transition so the baseline is
    // "old" on every clock.
    $doc->update(['created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)]);
    $doc->transitions()->update(['created_at' => now()->subDays(10)]);

    // A partial SDAO approval (first of two required) writes a fresh
    // DocumentTransition, but ApprovalEngine::approve() returns early on
    // unmet quorum WITHOUT saving the Document — documents.updated_at stays
    // stale even though real activity just happened.
    $this->engine->approve($doc, $this->sdaoA);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('oldestInReview', 1)
            // Reading documents.updated_at instead would show ~10 days; the
            // partial approval's transition is what's actually recent.
            ->where('oldestInReview.0.daysSinceActivity', 0)
        );
});

test('quick stats carry a weekly baseline for awaiting-review and pending-accounts only', function () {
    // This week: one short-chain submission, one newly unverified account.
    inReviewRegistration($this->org, $this->engine, $this->studentAlpha);
    User::factory()->create(['account_status' => AccountStatus::Unverified]);

    // Last week: one more of each, backdated.
    $lastWeekDoc = inReviewRegistration($this->itGuild, $this->engine, $this->studentAlpha);
    $lastWeekDoc->transitions()->where('action', 'submitted')->update(['created_at' => now()->subWeek()]);

    // User::created_at isn't in the model's #[Fillable(...)] list, so a
    // plain ->update() silently drops it — force it directly instead.
    $lastWeekUser = User::factory()->create(['account_status' => AccountStatus::Unverified]);
    $lastWeekUser->forceFill(['created_at' => now()->subWeek()])->save();

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('quickStats.0.weekly.thisWeek', 1)
            ->where('quickStats.0.weekly.lastWeek', 1)
            ->where('quickStats.0.weekly.delta', 0)
            ->where('quickStats.0.weekly.noun', 'submitted')
            ->missing('quickStats.1.weekly')
            ->where('quickStats.2.weekly.thisWeek', 1)
            ->where('quickStats.2.weekly.lastWeek', 1)
            ->where('quickStats.2.weekly.delta', 0)
            ->where('quickStats.2.weekly.noun', 'registered')
            ->missing('quickStats.3.weekly')
        );
});

test('org compliance lists organizations with pending items and organizations not yet renewed this year', function () {
    inReviewRegistration($this->org, $this->engine, $this->studentAlpha);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('orgCompliance.pending', 1)
            ->where('orgCompliance.pending.0.organizationName', 'Computing Society')
            ->where('orgCompliance.pending.0.count', 1)
            // No org in this seed has an approved renewal for the current
            // academic year, so every org appears here.
            ->where('orgCompliance.notRenewed', fn ($orgs) => count($orgs) >= 1)
        );
});
