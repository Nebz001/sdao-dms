<?php

use App\Enums\AccountStatus;
use App\Identity\Admin\RejectAccount;
use App\Identity\Admin\VerifyAccount;
use App\Mail\AccountRejectedMail;
use App\Mail\AccountVerifiedMail;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();

    Mail::fake();
});

test('verifying a pending account sends AccountVerifiedMail to that account', function () {
    $account = User::factory()->unverifiedAccount()->create();

    app(VerifyAccount::class)->execute($this->sdaoA, $account);

    Mail::assertQueued(AccountVerifiedMail::class, fn (AccountVerifiedMail $mail) => $mail->hasTo($account->email));
    Mail::assertNotQueued(AccountRejectedMail::class);
});

test('rejecting a pending account sends AccountRejectedMail to that account', function () {
    $account = User::factory()->unverifiedAccount()->create();

    app(RejectAccount::class)->execute($this->sdaoA, $account);

    Mail::assertQueued(AccountRejectedMail::class, fn (AccountRejectedMail $mail) => $mail->hasTo($account->email));
    Mail::assertNotQueued(AccountVerifiedMail::class);
});

test('a mail dispatch failure is logged but does not prevent account verification from succeeding', function () {
    Log::spy();

    $pending = Mockery::mock(PendingMail::class);
    $pending->shouldReceive('queue')->andThrow(new RuntimeException('smtp boom: 550 5.7.0 Too many emails per second'));
    Mail::shouldReceive('to')->andReturn($pending);

    $account = User::factory()->unverifiedAccount()->create();

    $result = app(VerifyAccount::class)->execute($this->sdaoA, $account);

    expect($result->account_status)->toBe(AccountStatus::Verified);

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message) => $message === 'Account-verified notification failed to dispatch')
        ->atLeast()->once();
});
