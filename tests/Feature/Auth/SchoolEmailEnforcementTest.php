<?php

use App\Enums\Role;
use App\Models\User;
use App\Notifications\ApproverProvisionedNotification;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * Every account write path (registration, profile update, admin
 * provisioning) shares App\Concerns\ProfileValidationRules::emailRules(),
 * which is where App\Rules\SchoolEmailDomain is applied. This proves the
 * rule actually reaches all three call sites, not just the shared trait in
 * isolation (see tests/Feature/SchoolEmailDomainRuleTest.php for that).
 */
test('self-registration rejects a personal email', function () {
    Mail::fake();

    $response = $this->post(route('register.store'), [
        'name' => 'Someone',
        'email' => 'someone@gmail.com',
        'id_number' => '2023-182854',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    expect(User::where('email', 'someone@gmail.com')->exists())->toBeFalse();
});

test('self-registration rejects a staff-domain email — students use the student domain', function () {
    Mail::fake();

    $response = $this->post(route('register.store'), [
        'name' => 'Someone',
        'email' => 'someone@nu-lipa.edu.ph',
        'id_number' => '2023-182854',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
});

test('a logged-in user cannot change their profile email to a personal address', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => 'someone@yahoo.com',
    ]);

    $response->assertSessionHasErrors('email');
});

test('SDAO can provision an approver with a personal email, stored lowercase so login can find it', function () {
    Notification::fake();
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();

    $response = $this->actingAs($sdaoA)->post(route('admin.approvers.store'), [
        'name' => 'Carl Justin Magpantay',
        'email' => ' Magpantaycarljustin@Gmail.com ',
        'role' => Role::SdaoMember->value,
    ]);

    $response->assertSessionHasNoErrors();

    $created = User::where('email', 'magpantaycarljustin@gmail.com')->first();
    expect($created)->not->toBeNull()
        ->and($created->roleAssignments->pluck('role')->all())->toBe([Role::SdaoMember]);

    Notification::assertSentTo($created, ApproverProvisionedNotification::class);
});

test('SDAO cannot provision an approver on a student domain, in any letter case', function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();

    $this->actingAs($sdaoA)->post(route('admin.approvers.store'), [
        'name' => 'Fake Adviser',
        'email' => 'Fake-Adviser@Students.NU-Lipa.edu.ph',
        'role' => Role::Adviser->value,
    ])->assertSessionHasErrors('email');

    expect(User::where('email', 'fake-adviser@students.nu-lipa.edu.ph')->exists())->toBeFalse();
});

test('a personal-email approver can save their profile without changing their email', function () {
    $user = User::factory()->create(['email' => 'approver@gmail.com']);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Renamed Approver',
        'email' => 'approver@gmail.com',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->name)->toBe('Renamed Approver');
});

test('submitting the same email in a different letter case saves the profile and is not treated as a change', function () {
    $user = User::factory()->create(['email' => 'approver@gmail.com']);

    $response = $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Renamed Approver',
        'email' => '  Approver@GMAIL.com ',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));
    expect($user->fresh()->name)->toBe('Renamed Approver')
        ->and($user->fresh()->email)->toBe('approver@gmail.com')
        ->and(session('pending_profile_email'))->toBeNull();
});

test('a personal-email approver still cannot change to another personal address', function () {
    $user = User::factory()->create(['email' => 'approver@gmail.com']);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => 'someone-else@yahoo.com',
    ])->assertSessionHasErrors('email');
});

test('SDAO cannot provision an approver with a student-domain email — staff use the staff domain', function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();

    $response = $this->actingAs($sdaoA)->post(route('admin.approvers.store'), [
        'name' => 'Fake Adviser',
        'email' => 'fake-adviser@students.nu-lipa.edu.ph',
        'role' => Role::Adviser->value,
    ]);

    $response->assertSessionHasErrors('email');
});
