<?php

use App\Approval\ApprovalEngine;
use App\Enums\AccountStatus;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Enums\Role;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use App\Support\AcademicYear;
use Database\Seeders\WorkflowTemplateSeeder;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * The admin dashboard sits behind the same `can:access-admin` gate as the
 * rest of admin/* (AppServiceProvider::configureGates(), Role::SdaoMember
 * only) — mirrors DocumentArchiveAuthorizationTest's cast of non-SDAO roles.
 */
beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail(); // President, Computing Society
    $this->studentDelta = User::where('email', 'student-delta@students.nu-lipa.edu.ph')->firstOrFail(); // Secretary, Computing Society
    $this->adviserOne = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->deanCcit = User::where('email', 'dean-ccit@nu-lipa.edu.ph')->firstOrFail();
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

/**
 * An org that WAS approved but whose coverage has lapsed — the
 * OrganizationStatusResolver NeedsRenewal case, built directly rather than
 * through SubmitOrganizationRegistration/ApproveOrganizationRegistration
 * since only the resulting data (an Approved registration whose
 * covers_academic_year is behind the current academic year) matters here.
 */
function adminDashboardLapsedRegistration(Organization $org): Document
{
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Approved,
    ]);

    $startYear = (int) explode('-', AcademicYear::current())[0];
    $lapsedYear = ($startYear - 1).'-'.$startYear;

    OrganizationRegistrationDetail::factory()->create([
        'document_id' => $doc->id,
        'organization_type' => OrganizationType::CoCurricular,
        'academic_year' => $lapsedYear,
        'term' => 'first_term',
        'covers_academic_year' => $lapsedYear,
    ]);

    return $doc;
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

test('the pending accounts tile counts unverified accounts and mentions officer change requests', function () {
    User::factory()->create(['account_status' => AccountStatus::Unverified]);
    User::factory()->create(['account_status' => AccountStatus::Unverified]);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tiles.3.label', 'Pending accounts')
            ->where('tiles.3.count', 2)
            ->where('tiles.3.hint', 'Plus 0 officer change requests')
        );
});

test('the awaiting SDAO review tile counts in-review documents at the SDAO step', function () {
    inReviewRegistration($this->org, $this->engine, $this->studentAlpha);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tiles.0.label', 'Awaiting SDAO review')
            ->where('tiles.0.count', 1)
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

test('recent activity lists a submission with the actor, a badge, the org and a two-part summary', function () {
    inReviewRegistration($this->org, $this->engine, $this->studentAlpha);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('recentActivity', 1)
            ->where('recentActivity.0.actorName', $this->studentAlpha->name)
            ->where('recentActivity.0.badge', 'submitted')
            ->where('recentActivity.0.organizationName', 'Computing Society')
            ->where('recentActivity.0.summary', 'Organization Registration: Computing Society')
        );
});

test('recent activity leaves out advanced, completed and withdrawn rows so an approval shows once', function () {
    $doc = inReviewRegistration($this->org, $this->engine, $this->studentAlpha);
    $this->engine->approve($doc, $this->sdaoA);
    $this->engine->approve($doc->refresh(), $this->sdaoB);

    // submitted + approved + approved + completed were written; completed is hidden.
    expect($doc->transitions()->pluck('action')->map->value->all())->toContain('completed');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertInertia(fn ($page) => $page
            ->has('recentActivity', 3)
            ->where('recentActivity', fn ($rows) => collect($rows)->pluck('badge')->sort()->values()->all() === ['approved', 'approved', 'submitted'])
        );
});

test('recent activity names a return by how many sections were flagged', function () {
    $doc = inReviewRegistration($this->org, $this->engine, $this->studentAlpha);
    $this->engine->returnForRevision($doc, $this->sdaoA, 'Fix', ['organization_details', 'letter_of_intent']);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertInertia(fn ($page) => $page
            ->where('recentActivity.0.badge', 'returned')
            ->where('recentActivity.0.summary', '2 sections flagged on Organization Registration: Computing Society')
        );
});

test('a resubmission reads as submitted, revised and resubmitted', function () {
    $doc = inReviewRegistration($this->org, $this->engine, $this->studentAlpha);
    $this->engine->returnForRevision($doc, $this->sdaoA, 'Fix', ['general']);
    $this->engine->resubmit($doc->refresh(), $this->studentAlpha);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertInertia(fn ($page) => $page
            ->where('recentActivity.0.badge', 'submitted')
            ->where('recentActivity.0.summary', 'Revised and resubmitted Organization Registration: Computing Society')
        );
});

test('oldest in review uses the latest transition as the activity clock, not documents.updated_at', function () {
    $doc = inReviewRegistration($this->org, $this->engine, $this->studentAlpha);
    // Backdate both the document row and its transition so the baseline is
    // "old" on every clock.
    $doc->update(['created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)]);
    $doc->transitions()->update(['created_at' => now()->subDays(10)]);

    // A partial SDAO approval (first of two required) writes a fresh
    // DocumentTransition, but ApprovalEngine::approve() returns early on
    // unmet quorum WITHOUT saving the Document, so documents.updated_at stays
    // stale even though real activity just happened.
    $this->engine->approve($doc, $this->sdaoA);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('oldestInReview', 1)
            ->where('oldestInReview.0.title', 'Organization Registration: Computing Society')
            ->where('oldestInReview.0.approverName', 'SDAO Office')
            // Reading documents.updated_at instead would show about 10 days.
            ->where('oldestInReview.0.idleDays', 0)
            ->where('oldestInReview.0.tier', 'fresh')
        );
});

test('recent activity and oldest in review are each capped at 5 rows', function () {
    for ($i = 0; $i < 7; $i++) {
        inReviewRegistration($this->org, $this->engine, $this->studentAlpha);
    }

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('recentActivity', 5)
            ->has('oldestInReview', 5)
        );
});

test('org compliance counts covered approved organizations and lists the top organizations with pending items', function () {
    inReviewRegistration($this->org, $this->engine, $this->studentAlpha);

    // IT Guild WAS approved but its coverage is behind the current academic
    // year, so it is approved yet not covered.
    adminDashboardLapsedRegistration($this->itGuild);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('orgCompliance.renewalSeason', false)
            ->where('orgCompliance.total', 1)
            ->where('orgCompliance.done', 0)
            ->where('orgCompliance.pendingTotal', 1)
            ->where('orgCompliance.pending.0.organizationName', 'Computing Society')
            ->where('orgCompliance.pending.0.count', 1)
            ->where('orgCompliance.viewAllHref', route('admin.organizations.index', ['status' => 'needs_renewal']))
        );
});

test('org compliance does not count an organization that was never approved', function () {
    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('orgCompliance.total', 0)
            ->where('orgCompliance.done', 0)
        );
});

test('org compliance lists at most four organizations with pending items, most first', function () {
    foreach (range(1, 6) as $i) {
        $org = Organization::factory()->create(['name' => "Org {$i}"]);
        foreach (range(1, $i) as $n) {
            Document::factory()->create([
                'form_type' => FormType::OrganizationRegistration,
                'organization_id' => $org->id,
                'status' => DocumentStatus::Draft,
            ]);
        }
    }

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.dashboard.index'))
        ->assertInertia(fn ($page) => $page
            ->where('orgCompliance.pendingTotal', 6)
            ->has('orgCompliance.pending', 4)
            ->where('orgCompliance.pending.0.organizationName', 'Org 6')
            ->where('orgCompliance.pending.0.count', 6)
        );
});
