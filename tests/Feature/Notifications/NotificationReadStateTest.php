<?php

use App\Identity\Admin\VerifyAccount;
use App\Models\User;
use App\Notifications\AccountVerifiedNotification;
use Database\Seeders\IdentitySeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(IdentitySeeder::class);
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->account = User::factory()->unverifiedAccount()->create();

    // A real notification, generated through the actual trigger rather than
    // hand-inserted, so this test also proves the row NotificationController
    // operates on is exactly what VerifyAccount produces.
    app(VerifyAccount::class)->execute($this->sdaoA, $this->account);

    $this->notification = $this->account->notifications()->firstOrFail();
});

test('marking a notification read updates read_at', function () {
    expect($this->notification->read_at)->toBeNull();

    $this->actingAs($this->account)
        ->patch(route('notifications.read', $this->notification->id))
        ->assertRedirect();

    expect($this->notification->fresh()->read_at)->not->toBeNull();
});

test('marking another user\'s notification 404s and leaves it unread', function () {
    $other = User::factory()->create();

    $this->actingAs($other)
        ->patch(route('notifications.read', $this->notification->id))
        ->assertNotFound();

    expect($this->notification->fresh()->read_at)->toBeNull();
});

test('marking a nonexistent notification id 404s', function () {
    $this->actingAs($this->account)
        ->patch(route('notifications.read', (string) Str::uuid()))
        ->assertNotFound();
});

test('mark all as read clears every unread notification for the user and flashes a toast', function () {
    // A second row for the same account, alongside the one from beforeEach —
    // hand-inserted here since the trigger itself is already covered by
    // tests/Feature/Notifications/DocumentNotificationRecordingTest.php;
    // this test is only about the bulk read-state action.
    $this->account->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => AccountVerifiedNotification::class,
        'data' => ['kind' => 'account_verified', 'title' => 'second', 'body' => '', 'url' => route('dashboard')],
        'read_at' => null,
    ]);

    expect($this->account->unreadNotifications()->count())->toBe(2);

    $this->actingAs($this->account)
        ->patch(route('notifications.read-all'))
        ->assertRedirect()
        ->assertSessionHas('flash.message', 'All notifications marked as read.');

    expect($this->account->unreadNotifications()->count())->toBe(0);
});

test('mark all as read does not touch another user\'s notifications', function () {
    $other = User::factory()->create();

    $this->actingAs($other)
        ->patch(route('notifications.read-all'));

    expect($this->notification->fresh()->read_at)->toBeNull();
});
