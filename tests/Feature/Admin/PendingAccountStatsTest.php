<?php

use App\Dashboard\PendingAccountStats;
use App\Enums\AccountStatus;
use App\Enums\Term;
use App\Identity\Admin\RejectAccount;
use App\Identity\Admin\RevertAccountReview;
use App\Identity\Admin\VerifyAccount;
use App\Models\User;
use App\Support\AcademicPeriod;
use App\Support\CurrentPeriod;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Carbon;

// 2026-10-14 is a Wednesday, inside 1st term 2026-2027 (Aug 1 to Dec 1 on the
// provisional term calendar). The term's first Monday-start week is Jul 27.
const STATS_NOW = '2026-10-14 12:00:00';

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    Carbon::setTestNow(STATS_NOW);
    $this->period = new AcademicPeriod('2026-2027', Term::FirstTerm);
    CurrentPeriod::set($this->period);
    $this->sdao = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->stats = app(PendingAccountStats::class);
});

afterEach(fn () => Carbon::setTestNow());

/** A self-registered student account (student email domain), registered N days before "now". */
function studentRegisteredDaysAgo(int $days, array $attributes = []): User
{
    return User::factory()->unverifiedAccount()->create([
        'email' => fake()->unique()->userName().'@students.nu-lipa.edu.ph',
        'created_at' => Carbon::parse(STATS_NOW)->subDays($days),
        ...$attributes,
    ]);
}

test('pending accounts fall into the 0 to 2, 3 to 7 and 8+ day buckets at the boundaries', function () {
    foreach ([0, 2, 3, 7, 8, 20] as $days) {
        studentRegisteredDaysAgo($days);
    }

    $queue = $this->stats->queue();

    expect($queue->pluck('days_waiting')->all())->toBe([20, 8, 7, 3, 2, 0])
        ->and($queue->pluck('tier')->all())->toBe(['overdue', 'overdue', 'aging', 'aging', 'fresh', 'fresh'])
        ->and($this->stats->buckets($queue))->toBe(['fresh' => 2, 'aging' => 2, 'overdue' => 2]);
});

test('the queue is oldest first so the first row is the oldest pending account', function () {
    $newer = studentRegisteredDaysAgo(1);
    $oldest = studentRegisteredDaysAgo(12);

    expect($this->stats->queue()->first()['id'])->toBe($oldest->id)
        ->and($this->stats->queue()->last()['id'])->toBe($newer->id);
});

test('with nothing pending the queue, buckets and oldest are all empty', function () {
    User::query()->where('account_status', AccountStatus::Unverified->value)->update(['account_status' => AccountStatus::Verified->value]);

    $queue = $this->stats->queue();

    expect($queue)->toBeEmpty()
        ->and($this->stats->buckets($queue))->toBe(['fresh' => 0, 'aging' => 0, 'overdue' => 0]);

    $this->actingAs($this->sdao)->withoutVite()
        ->get(route('admin.pending-accounts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/pending-accounts/index')
            ->where('accounts', [])
            ->where('oldest', null)
            ->where('buckets', ['fresh' => 0, 'aging' => 0, 'overdue' => 0])
        );
});

test('signups are counted per Monday-start week across the term, student accounts only', function () {
    // The seeded demo students were registered during this term too, so assert deltas.
    $before = $this->stats->termActivity($this->period)['signedUp'];

    // Mon Aug 3 -> week index 1. Sun Oct 11 -> week index 10. Tue Oct 13 -> the running week (index 11).
    studentRegisteredDaysAgo(0, ['created_at' => '2026-08-03 08:00:00']);
    studentRegisteredDaysAgo(0, ['created_at' => '2026-10-11 23:30:00']);
    studentRegisteredDaysAgo(0, ['created_at' => '2026-10-13 09:00:00']);
    // Outside the term: before Aug 1, and after now but still inside the term window.
    studentRegisteredDaysAgo(0, ['created_at' => '2026-07-20 09:00:00']);
    // A provisioned staff account created in the term never counts as a signup.
    User::factory()->create(['email' => 'new.staff@nu-lipa.edu.ph', 'created_at' => '2026-09-01 09:00:00']);

    $signedUp = $this->stats->termActivity($this->period)['signedUp'];

    $delta = fn (int $week): int => $signedUp['weeks'][$week] - $before['weeks'][$week];

    expect($signedUp['weeks'])->toHaveCount(12)
        ->and($delta(1))->toBe(1)
        ->and($delta(10))->toBe(1)
        ->and($delta(11))->toBe(1)
        ->and($signedUp['thisWeek'] - $before['thisWeek'])->toBe(1)
        ->and($signedUp['total'] - $before['total'])->toBe(3);
});

test('decided counts split verified from rejected and only count decisions made in the term', function () {
    $inTermVerified = studentRegisteredDaysAgo(30);
    $inTermRejected = studentRegisteredDaysAgo(30);
    $lastTerm = studentRegisteredDaysAgo(120);

    $inTermVerified->update(['account_status' => AccountStatus::Verified, 'account_reviewed_at' => '2026-09-20 10:00:00']);
    $inTermRejected->update(['account_status' => AccountStatus::Rejected, 'account_reviewed_at' => '2026-10-01 10:00:00']);
    $lastTerm->update(['account_status' => AccountStatus::Verified, 'account_reviewed_at' => '2026-05-01 10:00:00']);

    expect($this->stats->termActivity($this->period)['decided'])
        ->toBe(['total' => 2, 'verified' => 1, 'rejected' => 1]);
});

test('before the term has started there are no weeks and a zero this-week count', function () {
    Carbon::setTestNow('2026-07-01 12:00:00');

    $signedUp = $this->stats->termActivity($this->period)['signedUp'];

    expect($signedUp['weeks'])->toBe([])->and($signedUp['thisWeek'])->toBe(0);
});

test('verifying and rejecting stamp the review time and undo clears it', function () {
    $verified = studentRegisteredDaysAgo(3);
    $rejected = studentRegisteredDaysAgo(3);

    app(VerifyAccount::class)->execute($this->sdao, $verified);
    app(RejectAccount::class)->execute($this->sdao, $rejected);

    expect($verified->fresh()->account_reviewed_at->toDateTimeString())->toBe('2026-10-14 12:00:00')
        ->and($rejected->fresh()->account_reviewed_at)->not->toBeNull();

    app(RevertAccountReview::class)->execute($this->sdao, $verified);

    expect($verified->fresh()->account_reviewed_at)->toBeNull();
});

test('the page sends the buckets and oldest account, then the deferred term activity', function () {
    $oldest = studentRegisteredDaysAgo(9);
    studentRegisteredDaysAgo(1);

    $this->actingAs($this->sdao)->withoutVite()
        ->get(route('admin.pending-accounts.index'))
        ->assertInertia(fn ($page) => $page
            ->where('oldest.id', $oldest->id)
            ->where('oldest.days_waiting', 9)
            ->where('oldest.tier', 'overdue')
            ->where('buckets', ['fresh' => 1, 'aging' => 0, 'overdue' => 1])
            ->missing('termActivity')
            ->loadDeferredProps(fn ($deferred) => $deferred
                ->has('termActivity.signedUp.weeks')
                ->has('termActivity.decided.total')
            )
        );
});
