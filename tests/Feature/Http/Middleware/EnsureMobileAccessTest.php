<?php

use App\Models\User;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\TestIdentitySeeder;

beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class]);

    Route::middleware(['api', 'auth:sanctum', 'mobile.access'])
        ->get('/api/_test/mobile-only', fn () => response()->json(['ok' => true]));
});

test('an approver with mobile access passes through', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $token = $adviser->createToken('test')->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/_test/mobile-only')
        ->assertOk()
        ->assertJson(['ok' => true]);
});

test('a student is blocked with the contract 403 message', function () {
    $student = User::factory()->create();
    $student->roleAssignments()->create(['role' => 'student']);
    $token = $student->createToken('test')->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/_test/mobile-only')
        ->assertStatus(403)
        ->assertJson(['message' => 'You are not allowed to perform this action.']);
});

test('an unauthenticated request is rejected before the access check runs', function () {
    $this->getJson('/api/_test/mobile-only')
        ->assertStatus(401)
        ->assertJson(['message' => 'Unauthenticated.']);
});

test('a rejected approver account is blocked even with a valid, still-existing token', function () {
    // The token itself is fine (not deleted, not expired) — access is
    // re-checked EVERY request via MobileAccess::canAccess(), not just
    // at token-issue time, so a token minted while the account was
    // Verified still fails the moment the account is Rejected.
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $token = $adviser->createToken('test')->plainTextToken;
    $adviser->forceFill(['account_status' => 'rejected'])->save();

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/_test/mobile-only')
        ->assertStatus(403)
        ->assertJson(['message' => 'You are not allowed to perform this action.']);
});
