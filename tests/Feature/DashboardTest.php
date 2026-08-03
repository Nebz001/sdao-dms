<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Support\AcademicYear;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('a bare verified user with no role or org sees no dashboard sections, but is offered the founding flow', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('myOrganization', null)
            ->where('proposalsAtMyStep', null)
            ->where('auth.canProposeOrganization', true)
        );
});

test('auth.canProposeOrganization mirrors DocumentPolicy::propose exactly — true for any verified, unaffiliated user regardless of role', function () {
    // SDAO/adviser/etc. roles are RoleAssignment rows, not OrganizationMembership
    // rows, so DocumentPolicy::propose() (which only checks membership) is true
    // for them too — a pre-existing, intentionally out-of-scope looseness. The
    // sidebar is what keeps "Submit Registration" student-only, by additionally
    // checking isSdao/reviewsProposals before showing the item — see
    // app-sidebar.tsx's canFoundOrganization. This test documents that the
    // shared prop itself is a verbatim, role-agnostic mirror of the Gate.
    //
    // Hits admin.dashboard.index rather than dashboard: an SDAO member now
    // redirects away from /dashboard (see the redirect tests below), but
    // auth.* props are shared globally by HandleInertiaRequests, so any page
    // they can actually reach proves the same thing.
    $sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();

    $this->actingAs($sdaoA)
        ->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.canProposeOrganization', true));
});

test('a student officer is not offered the founding flow (they already have an organization)', function () {
    $studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();

    $this->actingAs($studentAlpha)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.canProposeOrganization', false));
});

test('an SDAO member visiting /dashboard is redirected to the admin dashboard', function () {
    $sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();

    $this->actingAs($sdaoA)
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.dashboard.index'));
});

test('a non-SDAO approver still sees the light dashboard, unaffected by the SDAO redirect', function () {
    $adviserOne = User::where('email', 'adviser-one@sdao.test')->firstOrFail();

    $this->actingAs($adviserOne)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('dashboard'));
});

test('a student officer sees their organization\'s documents needing attention', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();

    Document::create([
        'form_type' => FormType::OrganizationRenewal,
        'variant' => null,
        'title' => 'Returned Renewal',
        'status' => DocumentStatus::Returned,
        'current_step_position' => 1,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => $studentAlpha->id,
    ]);

    $this->actingAs($studentAlpha);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('myOrganization.name', 'Computing Society')
            ->where('myOrganization.count', 1)
            ->where('myOrganization.items.0.title', 'Returned Renewal')
        );
});

test('a proposal-chain approver sees proposals currently at their step', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $adviserOne = User::where('email', 'adviser-one@sdao.test')->firstOrFail();

    $activityDoc = Document::create([
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
        'document_id' => $activityDoc->id,
        'academic_year' => AcademicYear::current(),
        'term' => 'first_term',
    ]);
    $activity = CalendarActivity::create([
        'activity_calendar_id' => $cal->id,
        'name' => 'Dashboard Test Activity',
        'venue' => 'Main Hall',
        'activity_date' => '2026-10-30',
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);

    $draft = app(StartProposalDraft::class)->execute(
        actor: $studentAlpha,
        organization: $org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );
    app(SubmitActivityProposal::class)->execute(
        actor: $studentAlpha,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    // Regular on-calendar chain starts at the adviser (CLAUDE.md #8).
    $this->actingAs($adviserOne);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('proposalsAtMyStep.count', 1)
        );
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});
