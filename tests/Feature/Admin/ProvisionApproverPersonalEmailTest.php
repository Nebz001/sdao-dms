<?php

use App\Enums\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Notifications\ApproverProvisionedNotification;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Notification;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * Approvers are provisioned by SDAO with the address they should be reached
 * at. The staff domain is the expected case, but a personal address is
 * allowed (only a STUDENT-domain address is refused, so an approver is never
 * mistaken for a student). The real SDAO approver replaces the seeded test
 * approver A through the same endpoint.
 */
beforeEach(function () {
    Notification::fake();
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class]);
    $this->testApproverA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdao = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
});

function provisionPayload(array $overrides = []): array
{
    return [
        'first_name' => 'Carl Justin',
        'last_name' => 'Magpantay',
        'email' => 'carl.magpantay@gmail.com',
        'role' => Role::SdaoMember->value,
        ...$overrides,
    ];
}

test('an approver can be provisioned on a personal address, and the staff domain stays valid', function (string $address) {
    $this->actingAs($this->sdao)->post(route('admin.approvers.store'), provisionPayload(['email' => $address]))
        ->assertSessionHasNoErrors();

    expect(User::where('email', $address)->exists())->toBeTrue();
})->with([
    'gmail' => 'carl.magpantay@gmail.com',
    'a custom domain' => 'carl@my-own-domain.dev',
    'the staff domain (the expected case)' => 'carl.magpantay@nu-lipa.edu.ph',
]);

test('a student-domain address is still refused for an approver', function () {
    $this->actingAs($this->sdao)->post(route('admin.approvers.store'), provisionPayload(['email' => 'carl@students.nu-lipa.edu.ph']))
        ->assertSessionHasErrors(['email' => 'The email cannot be a student email address. Use a staff or personal address.']);

    expect(User::where('email', 'carl@students.nu-lipa.edu.ph')->exists())->toBeFalse();
});

test('an approver on a personal address is mailed there: the welcome message and every later notification', function () {
    $this->actingAs($this->sdao)->post(route('admin.approvers.store'), provisionPayload());

    $approver = User::where('email', 'carl.magpantay@gmail.com')->firstOrFail();
    expect($approver->personal_email)->toBeNull();
    expect($approver->notificationEmail())->toBe('carl.magpantay@gmail.com');

    Notification::assertSentTo($approver, ApproverProvisionedNotification::class, function (ApproverProvisionedNotification $notification) use ($approver) {
        $mail = $notification->toMail($approver);

        return collect($mail->to)->pluck('address')->all() === ['carl.magpantay@gmail.com'];
    });
});

test('the real SDAO approver on a personal address replaces the test approver A cleanly', function () {
    $this->actingAs($this->sdao)->post(route('admin.approvers.store'), provisionPayload([
        'replaces_user_id' => $this->testApproverA->id,
        'deactivate_replaced' => true,
    ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.approvers.index'));

    $real = User::where('email', 'carl.magpantay@gmail.com')->firstOrFail();

    // The new account holds the seat, ready for first login (one time password, then change it).
    expect(RoleAssignment::where('user_id', $real->id)->where('role', Role::SdaoMember->value)->exists())->toBeTrue();
    expect($real->must_change_password)->toBeTrue();
    expect($real->email_verified_at)->not->toBeNull();
    expect($real->isVerifiedAccount())->toBeTrue();

    // The test approver A no longer holds it and can no longer sign in.
    expect(RoleAssignment::where('user_id', $this->testApproverA->id)->where('role', Role::SdaoMember->value)->exists())->toBeFalse();
    expect($this->testApproverA->fresh()->isDeactivated())->toBeTrue();

    // Exactly one welcome message, to the personal address.
    Notification::assertSentToTimes($real, ApproverProvisionedNotification::class, 1);
});
