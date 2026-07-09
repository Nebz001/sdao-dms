<?php

use App\Identity\Admin\RejectAccount;
use App\Identity\Admin\VerifyAccount;
use App\Mail\AccountRejectedMail;
use App\Mail\AccountVerifiedMail;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();

    Mail::fake();
});

test('verifying a pending account sends AccountVerifiedMail to that account', function () {
    $account = User::factory()->unverifiedAccount()->create();

    app(VerifyAccount::class)->execute($this->sdaoA, $account);

    Mail::assertSent(AccountVerifiedMail::class, fn (AccountVerifiedMail $mail) => $mail->hasTo($account->email));
    Mail::assertNotSent(AccountRejectedMail::class);
});

test('rejecting a pending account sends AccountRejectedMail to that account', function () {
    $account = User::factory()->unverifiedAccount()->create();

    app(RejectAccount::class)->execute($this->sdaoA, $account);

    Mail::assertSent(AccountRejectedMail::class, fn (AccountRejectedMail $mail) => $mail->hasTo($account->email));
    Mail::assertNotSent(AccountVerifiedMail::class);
});
