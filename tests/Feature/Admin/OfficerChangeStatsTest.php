<?php

use App\Dashboard\OfficerChangeStats;
use App\Enums\OfficerChangeRequestStatus;
use App\Enums\OfficerPosition;
use App\Enums\Term;
use App\Models\OfficerChangeRequest;
use App\Models\Organization;
use App\Models\User;
use App\Support\AcademicPeriod;
use App\Support\CurrentPeriod;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Carbon;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

// 2026-10-14 is a Wednesday, inside 1st term 2026-2027 (Aug 1 to Dec 1 on the
// provisional term calendar).
const OFFICER_STATS_NOW = '2026-10-14 12:00:00';

beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    Carbon::setTestNow(OFFICER_STATS_NOW);
    $this->period = new AcademicPeriod('2026-2027', Term::FirstTerm);
    CurrentPeriod::set($this->period);
    $this->sdao = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->stats = app(OfficerChangeStats::class);
});

afterEach(fn () => Carbon::setTestNow());

/** A request attributed to a real officer of the org, filed N days before "now". */
function changeRequestFiledDaysAgo(int $days, array $attributes = []): OfficerChangeRequest
{
    $requester = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail(); // President

    return OfficerChangeRequest::factory()->create([
        'organization_id' => Organization::where('name', 'Computing Society')->firstOrFail()->id,
        'requested_by' => $requester->id,
        'position' => OfficerPosition::Secretary,
        'created_at' => Carbon::parse(OFFICER_STATS_NOW)->subDays($days),
        ...$attributes,
    ]);
}

/** A request decided N days before "now". Filed a day earlier unless told otherwise. */
function decidedChangeRequest(OfficerChangeRequestStatus $status, int $decidedDaysAgo, array $attributes = []): OfficerChangeRequest
{
    return changeRequestFiledDaysAgo($decidedDaysAgo + 1, [
        'status' => $status,
        'decided_at' => Carbon::parse(OFFICER_STATS_NOW)->subDays($decidedDaysAgo),
        ...$attributes,
    ]);
}

test('pending requests fall into the 0 to 2, 3 to 7 and 8+ day buckets at the boundaries', function () {
    foreach ([0, 2, 3, 7, 8, 20] as $days) {
        changeRequestFiledDaysAgo($days, ['position' => $days % 2 ? OfficerPosition::President : OfficerPosition::Secretary, 'organization_id' => Organization::factory()]);
    }

    $queue = $this->stats->queue();

    expect($queue->pluck('days_waiting')->all())->toBe([20, 8, 7, 3, 2, 0])
        ->and($queue->pluck('tier')->all())->toBe(['overdue', 'overdue', 'aging', 'aging', 'fresh', 'fresh'])
        ->and($this->stats->buckets($queue))->toBe(['fresh' => 2, 'aging' => 2, 'overdue' => 2]);
});

test('the queue is oldest first and leaves out decided and withdrawn requests', function () {
    $newer = changeRequestFiledDaysAgo(1, ['organization_id' => Organization::factory()]);
    $oldest = changeRequestFiledDaysAgo(6, ['organization_id' => Organization::factory()]);
    decidedChangeRequest(OfficerChangeRequestStatus::Approved, 2, ['organization_id' => Organization::factory()]);
    changeRequestFiledDaysAgo(4, ['status' => OfficerChangeRequestStatus::Withdrawn, 'organization_id' => Organization::factory()]);

    $queue = $this->stats->queue();

    expect($queue->pluck('id')->all())->toBe([$oldest->id, $newer->id]);
});

test('a row names the change, the requester position and the college', function () {
    $outgoing = User::where('email', 'student-delta@students.nu-lipa.edu.ph')->firstOrFail();
    $nominee = User::factory()->create(['name' => 'Ana Reyes']);
    changeRequestFiledDaysAgo(1, ['nominee_id' => $nominee->id, 'outgoing_user_id' => $outgoing->id]);

    $row = $this->stats->queue()->first();

    expect($row['change_type'])->toBe('Replace Secretary')
        ->and($row['change_detail'])->toBe("From {$outgoing->name} to Ana Reyes")
        ->and($row['requester_position'])->toBe('President')
        ->and($row['college'])->toBe($this->org->school?->name ?? 'No college');
});

test('a vacant seat reads as an add, and an organization with no school reads as no college', function () {
    $nominee = User::factory()->create(['name' => 'Ella Ramos']);
    $noCollege = Organization::factory()->create(['school_id' => null, 'program_id' => null]);
    changeRequestFiledDaysAgo(1, ['organization_id' => $noCollege->id, 'nominee_id' => $nominee->id, 'outgoing_user_id' => null]);

    $row = $this->stats->queue()->first();

    expect($row['change_type'])->toBe('Add Secretary')
        ->and($row['change_detail'])->toBe('Ella Ramos as Secretary')
        ->and($row['college'])->toBe('No college')
        ->and($row['requester_position'])->toBeNull();
});

test('requests filed this term are counted, and ones from an earlier term are not', function () {
    changeRequestFiledDaysAgo(1, ['organization_id' => Organization::factory()]);
    changeRequestFiledDaysAgo(2, ['organization_id' => Organization::factory()]);
    // Before Aug 1 2026, so in the previous academic year.
    changeRequestFiledDaysAgo(120, ['status' => OfficerChangeRequestStatus::Declined, 'decided_at' => Carbon::parse(OFFICER_STATS_NOW)->subDays(119), 'organization_id' => Organization::factory()]);
    changeRequestFiledDaysAgo(3, ['status' => OfficerChangeRequestStatus::Withdrawn, 'organization_id' => Organization::factory()]);

    $submitted = $this->stats->termActivity($this->period)['submitted'];

    expect($submitted['total'])->toBe(2)
        // Mon Oct 12 starts the running week, so the 1 and 2 day old ones are Oct 13 and 12.
        ->and($submitted['thisWeek'])->toBe(2)
        ->and(array_sum($submitted['weeks']))->toBe(2)
        ->and(count($submitted['weeks']))->toBeGreaterThan(1);
});

test('decided this term counts approved and declined by decision date, not filing date', function () {
    decidedChangeRequest(OfficerChangeRequestStatus::Approved, 3, ['organization_id' => Organization::factory()]);
    decidedChangeRequest(OfficerChangeRequestStatus::Approved, 5, ['organization_id' => Organization::factory()]);
    decidedChangeRequest(OfficerChangeRequestStatus::Declined, 9, ['organization_id' => Organization::factory()]);
    // Filed last academic year but decided this term: still counts as decided this term.
    changeRequestFiledDaysAgo(200, [
        'status' => OfficerChangeRequestStatus::Approved,
        'decided_at' => Carbon::parse(OFFICER_STATS_NOW)->subDay(),
        'organization_id' => Organization::factory(),
    ]);
    // Decided in an earlier term.
    decidedChangeRequest(OfficerChangeRequestStatus::Declined, 100, ['organization_id' => Organization::factory()]);
    // Withdrawn requests were never reviewed.
    changeRequestFiledDaysAgo(4, ['status' => OfficerChangeRequestStatus::Withdrawn, 'decided_at' => Carbon::parse(OFFICER_STATS_NOW)->subDays(2), 'organization_id' => Organization::factory()]);

    expect($this->stats->termActivity($this->period)['decided'])
        ->toBe(['total' => 4, 'approved' => 3, 'declined' => 1]);
});

test('the term figures follow the current period setting, not the clock', function () {
    // The clock says 1st term, but SDAO has moved the system to 2nd term.
    $second = new AcademicPeriod('2026-2027', Term::SecondTerm);
    CurrentPeriod::set($second);
    changeRequestFiledDaysAgo(1, ['organization_id' => Organization::factory()]);

    expect($this->stats->termActivity(CurrentPeriod::get())['submitted']['total'])->toBe(0);
});

test('recently decided lists approved and declined from the last 30 days, newest first', function () {
    $older = decidedChangeRequest(OfficerChangeRequestStatus::Declined, 29, ['organization_id' => Organization::factory()]);
    $newer = decidedChangeRequest(OfficerChangeRequestStatus::Approved, 2, ['organization_id' => Organization::factory()]);
    decidedChangeRequest(OfficerChangeRequestStatus::Approved, 31, ['organization_id' => Organization::factory()]);
    changeRequestFiledDaysAgo(5, ['status' => OfficerChangeRequestStatus::Withdrawn, 'decided_at' => Carbon::parse(OFFICER_STATS_NOW)->subDay(), 'organization_id' => Organization::factory()]);
    changeRequestFiledDaysAgo(5, ['organization_id' => Organization::factory()]);

    $rows = $this->stats->recentlyDecided();

    expect($rows->pluck('id')->all())->toBe([$newer->id, $older->id])
        ->and($rows->pluck('result')->all())->toBe(['approved', 'declined']);
});

test('the page sends the queue, buckets, oldest, recent decisions, then the deferred term activity', function () {
    $oldest = changeRequestFiledDaysAgo(9, ['organization_id' => Organization::factory()]);
    changeRequestFiledDaysAgo(1, ['organization_id' => Organization::factory()]);
    decidedChangeRequest(OfficerChangeRequestStatus::Approved, 4, ['organization_id' => Organization::factory()]);

    $this->actingAs($this->sdao)->withoutVite()
        ->get(route('admin.officer-change-requests.index'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/officer-change-requests/index')
            ->has('requests', 2)
            ->where('oldest.id', $oldest->id)
            ->where('oldest.days_waiting', 9)
            ->where('oldest.tier', 'overdue')
            ->where('buckets', ['fresh' => 1, 'aging' => 0, 'overdue' => 1])
            ->has('recentDecisions', 1)
            ->where('recentDecisions.0.result', 'approved')
            ->missing('termActivity')
            ->loadDeferredProps(fn ($deferred) => $deferred
                ->where('termActivity.submitted.total', 3)
                ->where('termActivity.decided', ['total' => 1, 'approved' => 1, 'declined' => 0])
            )
        );
});

test('an empty queue has no oldest request and all-zero buckets', function () {
    $this->actingAs($this->sdao)->withoutVite()
        ->get(route('admin.officer-change-requests.index'))
        ->assertInertia(fn ($page) => $page
            ->has('requests', 0)
            ->where('oldest', null)
            ->where('buckets', ['fresh' => 0, 'aging' => 0, 'overdue' => 0])
            ->has('recentDecisions', 0)
        );
});
