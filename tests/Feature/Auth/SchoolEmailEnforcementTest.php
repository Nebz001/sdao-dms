<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Mail;

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

test('SDAO cannot provision an approver with a personal email', function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();

    $response = $this->actingAs($sdaoA)->post(route('admin.approvers.store'), [
        'name' => 'Fake Adviser',
        'email' => 'fake-adviser@protonmail.com',
        'role' => Role::Adviser->value,
    ]);

    $response->assertSessionHasErrors('email');
    expect(User::where('email', 'fake-adviser@protonmail.com')->exists())->toBeFalse();
});

test('SDAO cannot provision an approver with a student-domain email — staff use the staff domain', function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();

    $response = $this->actingAs($sdaoA)->post(route('admin.approvers.store'), [
        'name' => 'Fake Adviser',
        'email' => 'fake-adviser@students.nu-lipa.edu.ph',
        'role' => Role::Adviser->value,
    ]);

    $response->assertSessionHasErrors('email');
});
