<?php

use App\Models\User;
use App\Notifications\ApproverHandOffNotification;
use Database\Seeders\IdentitySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The full, filterable destination behind the bell dropdown's "View all
 * notifications" link — HandleInertiaRequests::notificationsFor() is a
 * tight, capped teaser (8 rows); this is where genuine browsing of a user's
 * notification history happens. Mirrors tests/Feature/Admin/ActivityLogTest.php's
 * shape for the same kind of capped-teaser-vs-full-page split.
 *
 * Rows are inserted directly into the `notifications` table (same precedent
 * as NotificationsSharedPropTest's "legacy absolute url" test) rather than
 * driven through the real approval engine — these tests are about the
 * listing's pagination/filter/order behavior, not approval mechanics.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class]);
    $this->user = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
});

function insertNotificationRow(User $user, string $title, ?string $readAt, ?string $createdAt = null): string
{
    $id = (string) Str::uuid();

    DB::table('notifications')->insert([
        'id' => $id,
        'type' => ApproverHandOffNotification::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode([
            'kind' => 'approver_hand_off',
            'title' => $title,
            'body' => 'Test body',
            'url' => '/dashboard',
            'status' => null,
        ]),
        'read_at' => $readAt,
        'created_at' => $createdAt ?? now(),
        'updated_at' => now(),
    ]);

    return $id;
}

test('an authenticated user can open their notifications page', function () {
    $this->actingAs($this->user)->withoutVite()
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('notifications/index'));
});

test('a guest is redirected to login', function () {
    $this->get(route('notifications.index'))->assertRedirect(route('login'));
});

test('defaults to showing every notification regardless of read state', function () {
    insertNotificationRow($this->user, 'Unread one', null);
    insertNotificationRow($this->user, 'Read one', now()->toISOString());

    $this->actingAs($this->user)->withoutVite()
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('notifications.data', 2)
            ->where('filters.status', 'all')
        );
});

test('the unread filter narrows to only unread rows', function () {
    insertNotificationRow($this->user, 'Unread one', null);
    insertNotificationRow($this->user, 'Read one', now()->toISOString());

    $this->actingAs($this->user)->withoutVite()
        ->get(route('notifications.index', ['status' => 'unread']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('notifications.data', 1)
            ->where('notifications.data.0.title', 'Unread one')
            ->where('filters.status', 'unread')
        );
});

test('the read filter narrows to only read rows', function () {
    insertNotificationRow($this->user, 'Unread one', null);
    insertNotificationRow($this->user, 'Read one', now()->toISOString());

    $this->actingAs($this->user)->withoutVite()
        ->get(route('notifications.index', ['status' => 'read']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('notifications.data', 1)
            ->where('notifications.data.0.title', 'Read one')
            ->where('filters.status', 'read')
        );
});

test('an unknown status value is ignored rather than emptying the page', function () {
    insertNotificationRow($this->user, 'Still here', null);

    $this->actingAs($this->user)->withoutVite()
        ->get(route('notifications.index', ['status' => 'not_a_real_status']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('notifications.data', 1)
            ->where('filters.status', 'all')
        );
});

test('rows are ordered unread-first, then newest-first within each group', function () {
    insertNotificationRow($this->user, 'Old read', now()->subDays(2)->toISOString(), now()->subDays(2)->toISOString());
    insertNotificationRow($this->user, 'New read', now()->subDay()->toISOString(), now()->subDay()->toISOString());
    insertNotificationRow($this->user, 'Old unread', null, now()->subHours(5)->toISOString());
    insertNotificationRow($this->user, 'New unread', null, now()->toISOString());

    $this->actingAs($this->user)->withoutVite()
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('notifications.data.0.title', 'New unread')
            ->where('notifications.data.1.title', 'Old unread')
            ->where('notifications.data.2.title', 'New read')
            ->where('notifications.data.3.title', 'Old read')
        );
});

test('results are paginated at 15 per page', function () {
    foreach (range(1, 20) as $i) {
        insertNotificationRow($this->user, "Row {$i}", null, now()->addSeconds($i)->toISOString());
    }

    $this->actingAs($this->user)->withoutVite()
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('notifications.data', 15)
            ->where('notifications.meta.last_page', 2)
            ->where('notifications.meta.total', 20)
            ->where('notifications.data.0.title', 'Row 20')
        );

    $this->actingAs($this->user)->withoutVite()
        ->get(route('notifications.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('notifications.data', 5));
});

test('unreadCount reflects the true total, independent of the current filter or page', function () {
    insertNotificationRow($this->user, 'Unread one', null);
    insertNotificationRow($this->user, 'Unread two', null);
    insertNotificationRow($this->user, 'Read one', now()->toISOString());

    $this->actingAs($this->user)->withoutVite()
        ->get(route('notifications.index', ['status' => 'read']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('unreadCount', 2));
});

test('a user only ever sees their own notifications', function () {
    $otherUser = User::factory()->create();
    insertNotificationRow($otherUser, "Someone else's notification", null);
    insertNotificationRow($this->user, 'Mine', null);

    $this->actingAs($this->user)->withoutVite()
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('notifications.data', 1)
            ->where('notifications.data.0.title', 'Mine')
        );
});
