<?php

use App\Models\User;
use Database\Seeders\WorkflowTemplateSeeder;
use Tests\Fixtures\TestIdentitySeeder;

beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class]);
});

test('a verified adviser can log in and receives a token', function () {
    $response = $this->postJson('/api/mobile/login', [
        'email' => 'adviser-one@nu-lipa.edu.ph',
        'password' => 'ict@1234',
        'device_name' => 'Test Phone',
    ]);

    $response->assertOk();
    expect($response->json('token'))->toBeString()->not->toBeEmpty();

    $user = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    expect($user->tokens()->count())->toBe(1);
});

test('an unknown email gives a 422 on the email field', function () {
    $this->postJson('/api/mobile/login', [
        'email' => 'nobody@nu-lipa.edu.ph',
        'password' => 'whatever',
        'device_name' => 'Test Phone',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

test('a wrong password gives a 422 on the password field', function () {
    $this->postJson('/api/mobile/login', [
        'email' => 'adviser-one@nu-lipa.edu.ph',
        'password' => 'wrong-password',
        'device_name' => 'Test Phone',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

test('a missing device_name gives a 422', function () {
    $this->postJson('/api/mobile/login', [
        'email' => 'adviser-one@nu-lipa.edu.ph',
        'password' => 'ict@1234',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('device_name');
});

test('a student cannot log in to the mobile app', function () {
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();

    $this->postJson('/api/mobile/login', [
        'email' => $student->email,
        'password' => 'ict@1234',
        'device_name' => 'Test Phone',
    ])
        ->assertStatus(403)
        ->assertJson(['message' => 'You are not allowed to perform this action.']);
});

test('an approver with an unverified email cannot log in', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $adviser->forceFill(['email_verified_at' => null])->save();

    $this->postJson('/api/mobile/login', [
        'email' => $adviser->email,
        'password' => 'ict@1234',
        'device_name' => 'Test Phone',
    ])->assertStatus(403);
});

test('an approver whose account is not Verified cannot log in', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $adviser->forceFill(['account_status' => 'unverified'])->save();

    $this->postJson('/api/mobile/login', [
        'email' => $adviser->email,
        'password' => 'ict@1234',
        'device_name' => 'Test Phone',
    ])->assertStatus(403);
});

test('an approver on a non-staff email domain cannot log in', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $adviser->forceFill(['email' => 'adviser-one@students.nu-lipa.edu.ph'])->save();

    $this->postJson('/api/mobile/login', [
        'email' => $adviser->email,
        'password' => 'ict@1234',
        'device_name' => 'Test Phone',
    ])->assertStatus(403);
});

test('leftover two-factor data on an approver account does not block mobile login', function () {
    // Regression guard for the 2FA removal, mobile-API side — mirrors the
    // web-login equivalent in AuthenticationTest.php.
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $adviser->forceFill([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->postJson('/api/mobile/login', [
        'email' => $adviser->email,
        'password' => 'ict@1234',
        'device_name' => 'Test Phone',
    ])->assertOk();
});

test('login is throttled after 5 attempts', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/mobile/login', [
            'email' => 'adviser-one@nu-lipa.edu.ph',
            'password' => 'wrong-password',
            'device_name' => 'Test Phone',
        ]);
    }

    $this->postJson('/api/mobile/login', [
        'email' => 'adviser-one@nu-lipa.edu.ph',
        'password' => 'ict@1234',
        'device_name' => 'Test Phone',
    ])->assertStatus(429);
});
