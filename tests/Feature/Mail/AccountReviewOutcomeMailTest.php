<?php

use App\Enums\AccountStatus;
use App\Identity\Admin\RejectAccount;
use App\Identity\Admin\VerifyAccount;
use App\Models\User;
use App\Notifications\AccountRejectedNotification;
use App\Notifications\AccountVerifiedNotification;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

// Delivery channel is now AccountVerifiedNotification/AccountRejectedNotification
// (mail + database off one class each — see ApproverHandOffNotification's
// docblock for the split), fired via $account->notify() — Notifiable's own
// dispatch, still intercepted correctly by Notification::fake(). Trigger
// unchanged: VerifyAccount::execute() / RejectAccount::execute().
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();

    Notification::fake();
});

test('verifying a pending account sends AccountVerifiedNotification to that account', function () {
    $account = User::factory()->unverifiedAccount()->create();

    app(VerifyAccount::class)->execute($this->sdaoA, $account);

    Notification::assertSentTo($account, AccountVerifiedNotification::class);
    Notification::assertSentTimes(AccountRejectedNotification::class, 0);
});

test('rejecting a pending account sends AccountRejectedNotification to that account', function () {
    $account = User::factory()->unverifiedAccount()->create();

    app(RejectAccount::class)->execute($this->sdaoA, $account);

    Notification::assertSentTo($account, AccountRejectedNotification::class);
    Notification::assertSentTimes(AccountVerifiedNotification::class, 0);
});

test('a notification dispatch failure is logged but does not prevent account verification from succeeding', function () {
    Log::spy();
    Notification::shouldReceive('send')->andThrow(new RuntimeException('smtp boom: 550 5.7.0 Too many emails per second'));

    $account = User::factory()->unverifiedAccount()->create();

    $result = app(VerifyAccount::class)->execute($this->sdaoA, $account);

    expect($result->account_status)->toBe(AccountStatus::Verified);

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message) => $message === 'Account-verified notification failed to dispatch')
        ->atLeast()->once();
});
