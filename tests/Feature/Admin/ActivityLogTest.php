<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * The full, filterable destination behind the admin dashboard's "View all
 * activity" link — AdminDashboardController::recentActivity() is capped to a
 * tight teaser (8 rows); this is where genuine browsing of
 * document_transitions happens. Sits behind the same `can:access-admin` gate
 * as the rest of admin/* — mirrors DocumentArchiveAuthorizationTest's cast of
 * non-SDAO roles.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $this->adviserOne = User::where('email', 'adviser-one@sdao.test')->firstOrFail();
    $this->deanCcit = User::where('email', 'dean-ccit@sdao.test')->firstOrFail();
});

/**
 * A directly-created transition — same precedent as DocumentArchiveTest's
 * archivedDocument() — for tests about the log's query/filter behavior, not
 * approval mechanics.
 */
function activityTransition(
    FormType $formType,
    Organization $org,
    User $actor,
    TransitionAction $action = TransitionAction::Submitted,
    string $title = 'Test Doc',
): DocumentTransition {
    $doc = Document::factory()->create([
        'form_type' => $formType,
        'organization_id' => $org->id,
        'status' => DocumentStatus::InReview,
        'current_step_position' => 1,
        'title' => $title,
    ]);

    return DocumentTransition::create([
        'document_id' => $doc->id,
        'actor_id' => $actor->id,
        'action' => $action,
        'from_status' => DocumentStatus::Draft,
        'to_status' => DocumentStatus::InReview,
        'step_position' => 1,
        'created_at' => now(),
    ]);
}

test('an SDAO member can open the activity log', function () {
    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index'))
        ->assertOk();
});

test('a student officer, an adviser, a dean, and a bare account all get 403 on the activity log', function () {
    $this->actingAs($this->studentAlpha)->withoutVite()->get(route('admin.activity.index'))->assertForbidden();
    $this->actingAs($this->adviserOne)->withoutVite()->get(route('admin.activity.index'))->assertForbidden();
    $this->actingAs($this->deanCcit)->withoutVite()->get(route('admin.activity.index'))->assertForbidden();

    $bareUser = User::factory()->create();
    $this->actingAs($bareUser)->withoutVite()->get(route('admin.activity.index'))->assertForbidden();
});

test('the log lists a transition for every form type', function (FormType $formType) {
    activityTransition($formType, $this->org, $this->studentAlpha);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/activity/index')
            ->where('transitions.data.0.formTypeLabel', $formType->label())
            ->has('transitions.data', 1)
        );
})->with([
    'registration' => [FormType::OrganizationRegistration],
    'renewal' => [FormType::OrganizationRenewal],
    'activity calendar' => [FormType::ActivityCalendar],
    'activity proposal' => [FormType::ActivityProposal],
    'after-activity report' => [FormType::AfterActivityReport],
]);

test('the form_type filter narrows the result set', function () {
    activityTransition(FormType::OrganizationRegistration, $this->org, $this->studentAlpha, title: 'A Registration');
    activityTransition(FormType::OrganizationRenewal, $this->org, $this->studentAlpha, title: 'A Renewal');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index', ['form_type' => FormType::OrganizationRenewal->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('transitions.data', 1)
            ->where('transitions.data.0.documentTitle', 'A Renewal')
        );
});

test('the action filter narrows the result set', function () {
    activityTransition(FormType::OrganizationRegistration, $this->org, $this->studentAlpha, TransitionAction::Submitted, 'Submitted Doc');
    activityTransition(FormType::OrganizationRegistration, $this->org, $this->sdaoA, TransitionAction::Rejected, 'Rejected Doc');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index', ['action' => TransitionAction::Rejected->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('transitions.data', 1)
            ->where('transitions.data.0.documentTitle', 'Rejected Doc')
            ->where('transitions.data.0.action', 'rejected')
        );
});

test('search matches the document title or the organization name', function () {
    activityTransition(FormType::OrganizationRegistration, $this->org, $this->studentAlpha, title: 'Findable Title');
    activityTransition(FormType::OrganizationRegistration, $this->itGuild, $this->studentAlpha, title: 'Something Else');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index', ['search' => 'Findable']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('transitions.data', 1));

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index', ['search' => 'IT Guild']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('transitions.data', 1)
            ->where('transitions.data.0.documentTitle', 'Something Else')
        );
});

test('an unknown form_type or action filter value is ignored rather than emptying the page', function () {
    activityTransition(FormType::OrganizationRegistration, $this->org, $this->studentAlpha, title: 'Still Here');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index', ['form_type' => 'not_a_real_type', 'action' => 'also_bogus']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('transitions.data', 1));
});

test('results are paginated at 20 per page, newest first', function () {
    foreach (range(1, 25) as $i) {
        $transition = activityTransition(FormType::OrganizationRegistration, $this->org, $this->studentAlpha, title: "Doc {$i}");
        // Force distinct, increasing created_at so ordering is deterministic
        // rather than relying on same-second factory timestamps.
        $transition->forceFill(['created_at' => now()->addSeconds($i)])->save();
    }

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('transitions.data', 20)
            ->where('transitions.meta.last_page', 2)
            ->where('transitions.meta.total', 25)
            ->where('transitions.data.0.documentTitle', 'Doc 25')
        );

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('transitions.data', 5));
});

test('stats.total reflects the filtered count', function () {
    activityTransition(FormType::OrganizationRegistration, $this->org, $this->studentAlpha, title: 'A');
    activityTransition(FormType::OrganizationRenewal, $this->org, $this->studentAlpha, title: 'B');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index', ['form_type' => FormType::OrganizationRenewal->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('stats.total', 1));
});

test('a real submission is visible end to end, with the actor and a working link', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $this->studentAlpha->id,
    ]);
    OrganizationRegistrationDetail::factory()->create([
        'document_id' => $doc->id,
        'organization_type' => OrganizationType::CoCurricular,
    ]);
    $this->engine->submit($doc, $this->studentAlpha);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('transitions.data', 1)
            ->where('transitions.data.0.actorName', $this->studentAlpha->name)
            ->where('transitions.data.0.action', 'submitted')
            ->where('transitions.data.0.href', route('review.registrations.show', $doc))
        );
});
