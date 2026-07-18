<?php

use App\Calendar\SubmitActivityCalendar;
use App\Enums\FormType;
use App\Enums\Term;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Support\CurrentTerm;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->action = app(SubmitActivityCalendar::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
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

// --- CurrentTerm accessor (App\Support\CurrentTerm) ---------------------

test('CurrentTerm::get() returns the default when no setting row exists yet', function () {
    expect(Setting::where('key', 'current_term')->exists())->toBeFalse();
    expect(CurrentTerm::get())->toBe(Term::FirstTerm);
});

test('CurrentTerm::set() then get() round-trips', function () {
    CurrentTerm::set(Term::SecondTerm);

    expect(CurrentTerm::get())->toBe(Term::SecondTerm);
});

test('CurrentTerm::set() twice keeps a single settings row (upsert, no duplicates)', function () {
    CurrentTerm::set(Term::SecondTerm);
    CurrentTerm::set(Term::ThirdTerm);

    expect(Setting::where('key', 'current_term')->count())->toBe(1);
    expect(CurrentTerm::get())->toBe(Term::ThirdTerm);
});

// --- Submission auto-uses the current term (required case 1) -----------

test('a new calendar submission automatically uses the current term with no user input', function () {
    CurrentTerm::set(Term::SecondTerm);

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
    CurrentTerm::set(Term::FirstTerm);

    $result = $this->action->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        activities: currentTermPayload()['activities'],
    );
    $doc = $result['document'];

    // Admin changes the current term AFTER this calendar was already submitted.
    CurrentTerm::set(Term::ThirdTerm);

    $doc->refresh();
    $doc->load('activityCalendar');
    expect($doc->activityCalendar->term)->toBe(Term::FirstTerm);
});

// --- Submission after a term change picks up the new term (required case 3) --

test('a submission made after a term change correctly picks up the new term', function () {
    CurrentTerm::set(Term::FirstTerm);
    $before = $this->action->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        activities: currentTermPayload()['activities'],
    );
    $before['document']->load('activityCalendar');
    expect($before['document']->activityCalendar->term)->toBe(Term::FirstTerm);

    CurrentTerm::set(Term::ThirdTerm);
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
    CurrentTerm::set(Term::SecondTerm);

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

test('an SDAO member can update the current term via the settings screen', function () {
    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route('admin.settings.term.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings/term')
            ->has('current')
            ->has('terms')
        );

    $response = $this->actingAs($this->sdaoA)->put(route('admin.settings.term.update'), [
        'term' => Term::ThirdTerm->value,
    ]);

    $response
        ->assertRedirect(route('admin.settings.term.edit'))
        ->assertSessionHas('flash', [
            'message' => 'Current term updated to '.Term::ThirdTerm->label().'. Already-submitted calendars are unchanged.',
        ]);
    expect(CurrentTerm::get())->toBe(Term::ThirdTerm);
});

test('a non-SDAO authenticated user is forbidden on the current-term settings routes', function () {
    $adviser = User::where('email', 'adviser-one@sdao.test')->firstOrFail();

    $this->actingAs($adviser)->get(route('admin.settings.term.edit'))->assertForbidden();
    $this->actingAs($adviser)->put(route('admin.settings.term.update'), [
        'term' => Term::ThirdTerm->value,
    ])->assertForbidden();
});
