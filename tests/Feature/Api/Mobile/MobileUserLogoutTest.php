<?php

use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Auth;

/**
 * Sanctum's guard caches the resolved user on the guard instance, and
 * that instance persists across multiple ->getJson()/->postJson() calls
 * within ONE test (the container is only rebuilt between test METHODS).
 * A real production request never shares that cache with any other
 * request, so any test that changes auth-relevant state and then makes a
 * SECOND authenticated call in the same test must force fresh
 * resolution, or it will silently assert against stale, cached state.
 */
function forgetCachedAuthGuards(): void
{
    Auth::forgetGuards();
}

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class]);

    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
});

test('mobile/user returns the expected shape and derived approver roles', function () {
    $token = $this->adviser->createToken('Phone')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/mobile/user');

    $response->assertOk()->assertJson([
        'id' => $this->adviser->id,
        'email' => $this->adviser->email,
        'name' => $this->adviser->name,
        'roles' => ['approver', 'adviser'],
        'mobile_access' => true,
        'capabilities' => ['can_access_mobile_review' => true],
    ]);
});

test('mobile/user without a token is 401', function () {
    $this->getJson('/api/mobile/user')
        ->assertStatus(401)
        ->assertJson(['message' => 'Unauthenticated.']);
});

test('mobile/user is 403 once the account loses its approver role', function () {
    $this->adviser->roleAssignments()->delete();
    $token = $this->adviser->createToken('Phone')->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/mobile/user')
        ->assertStatus(403);
});

test('logout returns 204 and deletes only the current token', function () {
    $tokenA = $this->adviser->createToken('Phone A')->plainTextToken;
    $tokenB = $this->adviser->createToken('Phone B')->plainTextToken;

    expect($this->adviser->tokens()->count())->toBe(2);

    $this->withHeaders(['Authorization' => "Bearer {$tokenA}"])
        ->postJson('/api/mobile/logout')
        ->assertNoContent();

    expect($this->adviser->tokens()->count())->toBe(1);
    expect($this->adviser->tokens()->first()->name)->toBe('Phone B');

    forgetCachedAuthGuards();

    // Token B still works.
    $this->withHeaders(['Authorization' => "Bearer {$tokenB}"])
        ->getJson('/api/mobile/user')
        ->assertOk();
});

test('a deleted token can no longer authenticate', function () {
    $token = $this->adviser->createToken('Phone')->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/mobile/logout')
        ->assertNoContent();

    forgetCachedAuthGuards();

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/mobile/user')
        ->assertStatus(401);
});
