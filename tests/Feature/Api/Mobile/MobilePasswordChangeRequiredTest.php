<?php

use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\WorkflowTemplateSeeder;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class]);

    $this->flagged = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->flagged->forceFill(['must_change_password' => true])->save();
});

test('a flagged user with the right password gets a 403 with the change code and no token', function () {
    $this->postJson('/api/mobile/login', [
        'email' => $this->flagged->email,
        'password' => 'ict@1234',
        'device_name' => 'Test Phone',
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'password_change_required')
        ->assertJsonMissingPath('token');

    expect($this->flagged->tokens()->count())->toBe(0);
});

test('a flagged user with a wrong password still gets the normal 422, so the flag is not revealed', function () {
    $this->postJson('/api/mobile/login', [
        'email' => $this->flagged->email,
        'password' => 'wrong-password',
        'device_name' => 'Test Phone',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password')
        ->assertJsonMissingPath('code');
});

test('an unflagged user still logs in and receives a token', function () {
    $this->flagged->forceFill(['must_change_password' => false])->save();

    $this->postJson('/api/mobile/login', [
        'email' => $this->flagged->email,
        'password' => 'ict@1234',
        'device_name' => 'Test Phone',
    ])
        ->assertOk()
        ->assertJsonStructure(['token']);
});

test('a token issued before the account was flagged gets the same 403 on every request', function () {
    $this->flagged->forceFill(['must_change_password' => false])->save();
    $token = $this->flagged->createToken('phone')->plainTextToken;

    $this->flagged->forceFill(['must_change_password' => true])->save();

    $this->withToken($token)
        ->getJson('/api/mobile/user')
        ->assertForbidden()
        ->assertJsonPath('code', 'password_change_required');
});

test('a flagged user can still log out of the mobile app', function () {
    $this->flagged->forceFill(['must_change_password' => false])->save();
    $token = $this->flagged->createToken('phone')->plainTextToken;
    $this->flagged->forceFill(['must_change_password' => true])->save();

    $this->withToken($token)->postJson('/api/mobile/logout')->assertNoContent();

    expect($this->flagged->tokens()->count())->toBe(0);
});
