<?php

use App\ActivityProposals\StartProposalDraft;
use App\Calendar\SubmitActivityCalendar;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Enums\Term;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Support\AcademicPeriod;
use App\Support\AcademicYear;
use App\Support\CurrentPeriod;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->action = app(SubmitActivityCalendar::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
});

function currentTermPayload(): array
{
    return [
        'activities' => [[
            'name' => 'JS Night',
            'venue' => 'Gymnasium',
            'activity_date' => '2026-09-15',
            'start_time' => '09:00',
            'end_time' => '12:00',
            // Required by StoreActivityCalendarRequest (Phase 2 item 7 slice 1) —
            // included here since this payload is also used by the one
            // HTTP-level test in this file (direct action calls ignore these).
            'sdg' => 'quality_education',
            'participant_program_assigned' => 'All Year Levels',
            'budget' => '5000.00',
        ]],
    ];
}

/**
 * Sets only the term half of the current period, keeping whatever academic
 * year is already stored — most of this file's tests only care about term
 * behavior and predate the (academic_year, term) pair, so this keeps them
 * mechanically unchanged.
 */
function setCurrentTerm(Term $term): void
{
    CurrentPeriod::set(new AcademicPeriod(CurrentPeriod::get()->academicYear, $term));
}

// --- CurrentPeriod accessor (App\Support\CurrentPeriod) ------------------

test('CurrentPeriod::get() falls back to the clock-derived period when no setting row exists yet', function () {
    expect(Setting::where('key', 'current_period')->exists())->toBeFalse();
    expect(CurrentPeriod::get()->term)->toBe(Term::FirstTerm);
});

test('CurrentPeriod::set() then get() round-trips', function () {
    setCurrentTerm(Term::SecondTerm);

    expect(CurrentPeriod::get()->term)->toBe(Term::SecondTerm);
});

test('CurrentPeriod::set() twice keeps a single settings row (upsert, no duplicates)', function () {
    setCurrentTerm(Term::SecondTerm);
    setCurrentTerm(Term::ThirdTerm);

    expect(Setting::where('key', 'current_period')->count())->toBe(1);
    expect(CurrentPeriod::get()->term)->toBe(Term::ThirdTerm);
});

test('CurrentPeriod::set() busts the cache immediately — no reboot needed to observe the change', function () {
    setCurrentTerm(Term::SecondTerm);
    expect(CurrentPeriod::get()->term)->toBe(Term::SecondTerm);

    setCurrentTerm(Term::ThirdTerm);
    expect(CurrentPeriod::get()->term)->toBe(Term::ThirdTerm);
});

test('AcademicYear::current() reflects CurrentPeriod::set()', function () {
    CurrentPeriod::set(new AcademicPeriod('2030-2031', Term::FirstTerm));

    expect(AcademicYear::current())->toBe('2030-2031');
});

test('AcademicYear::current() falls back to the clock-derived year when no setting row exists', function () {
    expect(Setting::where('key', 'current_period')->exists())->toBeFalse();
    expect(AcademicYear::current())->toBe(AcademicYear::forDate(now()));
});

// --- Submission auto-uses the current term (required case 1) -----------

test('a new calendar submission automatically uses the current term with no user input', function () {
    setCurrentTerm(Term::SecondTerm);

    $result = $this->action->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        activities: currentTermPayload()['activities'],
    );

    $result['document']->load('activityCalendar');
    expect($result['document']->activityCalendar->term)->toBe(Term::SecondTerm);
});

// --- Non-retroactive term change (required case 2) ----------------------

test('changing the current term does NOT retroactively change an already-submitted calendar', function () {
    setCurrentTerm(Term::FirstTerm);

    $result = $this->action->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        activities: currentTermPayload()['activities'],
    );
    $doc = $result['document'];

    // Admin changes the current term AFTER this calendar was already submitted.
    setCurrentTerm(Term::ThirdTerm);

    $doc->refresh();
    $doc->load('activityCalendar');
    expect($doc->activityCalendar->term)->toBe(Term::FirstTerm);
});

// --- Submission after a term change picks up the new term (required case 3) --

test('a submission made after a term change correctly picks up the new term', function () {
    setCurrentTerm(Term::FirstTerm);
    $before = $this->action->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        activities: currentTermPayload()['activities'],
    );
    $before['document']->load('activityCalendar');
    expect($before['document']->activityCalendar->term)->toBe(Term::FirstTerm);

    setCurrentTerm(Term::ThirdTerm);
    $after = $this->action->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        activities: [[
            'name' => 'Second Submission',
            'venue' => 'Auditorium',
            'activity_date' => '2026-10-01',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]],
    );
    $after['document']->load('activityCalendar');
    expect($after['document']->activityCalendar->term)->toBe(Term::ThirdTerm);

    // The first calendar is still untouched.
    $before['document']->refresh()->load('activityCalendar');
    expect($before['document']->activityCalendar->term)->toBe(Term::FirstTerm);
});

// --- Store no longer requires/accepts a term field (HTTP) ---------------

test('POST /activity-calendars succeeds with no term field and stores the current term', function () {
    setCurrentTerm(Term::SecondTerm);

    $response = $this->actingAs($this->studentAlpha)->post(route('activity-calendars.store'), currentTermPayload());

    $response->assertRedirect();
    $doc = Document::where('form_type', FormType::ActivityCalendar->value)
        ->where('organization_id', $this->org->id)
        ->latest('id')
        ->firstOrFail();
    $doc->load('activityCalendar');
    expect($doc->activityCalendar->term)->toBe(Term::SecondTerm);
});

// --- Admin settings screen (HTTP) ----------------------------------------

test('an SDAO member can update the current period via the settings screen', function () {
    Notification::fake();

    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route('admin.settings.period.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings/period')
            ->has('current')
            ->has('terms')
            ->has('academicYears')
            ->has('suggestedAcademicYearOnWrap')
            ->has('renewalNoticeRecipientCount')
        );

    $current = CurrentPeriod::get();

    // Advancing to 2nd term does not open renewal season — no notification copy.
    $response = $this->actingAs($this->sdaoA)->put(route('admin.settings.period.update'), [
        'term' => Term::SecondTerm->value,
        'academic_year' => $current->academicYear,
    ]);

    $newLabel = (new AcademicPeriod($current->academicYear, Term::SecondTerm))->label();

    $response
        ->assertRedirect(route('admin.settings.period.edit'))
        ->assertSessionHas('flash', fn ($flash) => str_starts_with(
            $flash['message'],
            "Current period updated to {$newLabel}. Documents already submitted or approved are unchanged.",
        ) && ! str_contains($flash['message'], 'Renewal season opened'));

    expect(CurrentPeriod::get()->term)->toBe(Term::SecondTerm);
    Notification::assertNothingSent();
});

test('setting the term to 3rd opens renewal season and the flash names the recipient count', function () {
    Notification::fake();

    $current = CurrentPeriod::get();
    setCurrentTerm(Term::SecondTerm);

    $response = $this->actingAs($this->sdaoA)->put(route('admin.settings.period.update'), [
        'term' => Term::ThirdTerm->value,
        'academic_year' => $current->academicYear,
    ]);

    $response->assertRedirect(route('admin.settings.period.edit'))
        ->assertSessionHas('flash', fn ($flash) => str_contains($flash['message'], 'Renewal season opened'));
});

test('academic_year must be two consecutive years', function () {
    $response = $this->actingAs($this->sdaoA)->put(route('admin.settings.period.update'), [
        'term' => Term::FirstTerm->value,
        'academic_year' => '2026-2030',
    ]);

    $response->assertSessionHasErrors('academic_year');
});

test('a non-SDAO authenticated user is forbidden on the current-period settings routes', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    $this->actingAs($adviser)->get(route('admin.settings.period.edit'))->assertForbidden();
    $this->actingAs($adviser)->put(route('admin.settings.period.update'), [
        'term' => Term::ThirdTerm->value,
        'academic_year' => CurrentPeriod::get()->academicYear,
    ])->assertForbidden();
});

// --- Off-calendar proposal regression: term dropdown removal (bug fix) --
//
// QA found the off-calendar Activity Proposal form still showed a
// per-submission Term dropdown. StartProposalDraft::startOffCalendar() now
// sources term from CurrentPeriod::get(), same as SubmitActivityCalendar, and
// StoreProposalStepOneRequest no longer validates a 'term' input.

test('a new off-calendar proposal automatically uses the current term with no user input', function () {
    setCurrentTerm(Term::SecondTerm);

    $document = app(StartProposalDraft::class)->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'title' => 'Off Calendar Term Test',
            'venue' => 'Room 101',
            'activity_date' => '2026-11-01',
            'start_time' => '09:00',
            'end_time' => '11:00',
            // Deliberately no 'term' key — proves it's no longer read from input.
        ],
    );

    $document->load('activityProposal.calendarActivity.calendar');
    expect($document->activityProposal->calendarActivity->calendar->term)->toBe(Term::SecondTerm);
});

test('POST /activity-proposals succeeds with no term field for off-calendar and stores the current term', function () {
    setCurrentTerm(Term::ThirdTerm);

    $response = $this->actingAs($this->studentAlpha)->post(route('activity-proposals.store'), [
        'calendar_mode' => 'off_calendar',
        'title' => 'HTTP Off Calendar Term Test',
        'venue' => 'Room 200',
        'activity_date' => '2026-11-05',
        'start_time' => '10:00',
        'end_time' => '12:00',
        // No 'term' field at all — matches the "no term field" HTTP
        // convention already proven for activity-calendars.store above.
        'activity_nature' => 'co_curricular',
        'activity_type' => 'seminar_workshop',
        'partner_organizations' => ['Partner Org'],
        'target_sdg' => 'quality_education',
        'proposed_budget' => '10000.00',
        'budget_source' => 'Org funds',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $doc = Document::where('form_type', FormType::ActivityProposal->value)
        ->where('organization_id', $this->org->id)
        ->latest('id')
        ->firstOrFail();
    $doc->load('activityProposal.calendarActivity.calendar');
    expect($doc->activityProposal->calendarActivity->calendar->term)->toBe(Term::ThirdTerm);
});

test('the off-calendar proposal create page does not render a term selector and shows the current term read-only', function () {
    setCurrentTerm(Term::SecondTerm);

    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('activity-proposals.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('activity-proposals/create')
            ->where('current_term_label', Term::SecondTerm->label())
            ->missing('terms')
        );
});
