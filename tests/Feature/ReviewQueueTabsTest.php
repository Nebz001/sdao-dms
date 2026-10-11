<?php

use App\Approval\ApprovalEngine;
use App\Enums\FormType;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Date;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * The status tabs on the four SDAO review queues (registrations, renewals,
 * calendars, reports), mirroring the Activity Proposals tabs. Reuses
 * shortChainInReviewDoc() from SectionFlagValidationTest.php (Pest loads all
 * Feature files up front).
 */
beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
    $this->student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
});

/** A document waiting at SDAO's step (a report passes its adviser first). */
function tabsDocAtSdao(FormType $formType): Document
{
    $doc = shortChainInReviewDoc($formType, test()->org, test()->engine, test()->student);

    if ($formType === FormType::AfterActivityReport) {
        test()->engine->approve($doc, test()->adviser);
        $doc->refresh();
    }

    return $doc;
}

dataset('review queue pages', [
    'registration' => [FormType::OrganizationRegistration, 'review.registrations.index'],
    'renewal' => [FormType::OrganizationRenewal, 'review.renewals.index'],
    'activity calendar' => [FormType::ActivityCalendar, 'review.activity-calendars.index'],
    'after-activity report' => [FormType::AfterActivityReport, 'review.reports.index'],
]);

test('each tab shows exactly the rows its count promises', function (FormType $formType, string $route) {
    $overdue = tabsDocAtSdao($formType);
    DocumentTransition::where('document_id', $overdue->id)->update(['created_at' => Date::now()->subDays(12)]);
    tabsDocAtSdao($formType);

    $approved = tabsDocAtSdao($formType);
    $this->engine->approve($approved, $this->sdaoA);
    $this->engine->approve($approved->refresh(), $this->sdaoB);

    $returned = tabsDocAtSdao($formType);
    $this->engine->returnForRevision($returned, $this->sdaoA, 'Fix it.');

    $rejected = tabsDocAtSdao($formType);
    $this->engine->reject($rejected, $this->sdaoA, 'No.');

    $visit = fn (?string $filter) => $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route($route, array_filter(['filter' => $filter])))->assertOk();

    // Counts never depend on the open tab, and Rejected has no tab of its own.
    foreach ([null, 'overdue', 'approved', 'returned', 'decided'] as $filter) {
        $visit($filter)->assertInertia(fn ($page) => $page
            ->where('tabCounts', ['pending' => 2, 'overdue' => 1, 'approved' => 1, 'returned' => 1])
            ->has('queue', 2)
            ->etc());
    }

    $visit(null)->assertInertia(fn ($page) => $page
        ->where('filter', null)->has('rows', 2)->where('pagination.meta.total', 2)
        ->where('rows.0.id', $overdue->id)
        ->etc());
    $visit('overdue')->assertInertia(fn ($page) => $page
        ->where('filter', 'overdue')->has('rows', 1)->where('rows.0.id', $overdue->id)
        ->where('rows.0.tier', 'overdue')
        ->etc());
    $visit('approved')->assertInertia(fn ($page) => $page
        ->has('rows', 1)->where('rows.0.result', 'approved')
        ->where('rows.0.decided_by', fn ($by) => in_array($by, [$this->sdaoA->name, $this->sdaoB->name], true))
        ->etc());
    $visit('returned')->assertInertia(fn ($page) => $page
        ->has('rows', 1)->where('rows.0.result', 'returned')
        ->etc());
    $visit('decided')->assertInertia(fn ($page) => $page
        ->where('filterLabel', 'All decisions')->has('rows', 3)->where('pagination.meta.total', 3)
        ->etc());

    // Rejected documents appear only under All decisions.
    $results = collect($visit('decided')->viewData('page')['props']['rows'])->pluck('result')->sort()->values()->all();
    expect($results)->toBe(['approved', 'rejected', 'returned']);
})->with('review queue pages');

test('an unrecognized filter falls back to the pending tab', function (FormType $formType, string $route) {
    tabsDocAtSdao($formType);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route($route, ['filter' => 'nonsense']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filter', null)->has('rows', 1)->etc());
})->with('review queue pages');

test('tabs are paginated inside the table, ten rows at a time', function (FormType $formType, string $route) {
    foreach (range(1, 12) as $ignored) {
        tabsDocAtSdao($formType);
    }

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route($route))
        ->assertInertia(fn ($page) => $page
            ->has('rows', 10)->where('pagination.meta.total', 12)
            ->where('pagination.meta.from', 1)->where('pagination.meta.to', 10)
            ->where('pagination.links.prev', null)->where('tabCounts.pending', 12)
            ->has('queue', 12)
            ->etc());

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route($route, ['page' => 2]))
        ->assertInertia(fn ($page) => $page
            ->has('rows', 2)->where('pagination.meta.from', 11)->where('pagination.meta.to', 12)
            ->where('pagination.links.next', null)
            ->etc());
})->with('review queue pages');

test('someone who cannot review sees empty tabs and zero counts', function (FormType $formType, string $route) {
    $doc = tabsDocAtSdao($formType);
    $this->engine->returnForRevision($doc, $this->sdaoA, 'Fix it.');
    tabsDocAtSdao($formType);

    foreach ([null, 'decided'] as $filter) {
        $this->actingAs($this->student)->withoutVite()
            ->get(route($route, array_filter(['filter' => $filter])))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tabCounts', ['pending' => 0, 'overdue' => 0, 'approved' => 0, 'returned' => 0])
                ->has('rows', 0)->where('pagination.meta.total', 0)
                ->etc());
    }
})->with('review queue pages');
