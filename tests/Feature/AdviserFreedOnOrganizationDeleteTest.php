<?php

use App\Enums\Role;
use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\User;

/**
 * Deleting an organization must free its adviser back to the available pool,
 * not destroy their adviser role entirely. The role_assignments.organization_id
 * foreign key is nullOnDelete (not cascadeOnDelete) specifically so this holds.
 */
test('deleting an organization nulls its adviser role_assignment instead of deleting it', function () {
    $org = Organization::factory()->create();
    $adviser = User::factory()->create();
    $assignment = RoleAssignment::factory()->create([
        'user_id' => $adviser->id,
        'role' => Role::Adviser,
        'organization_id' => $org->id,
    ]);

    $org->delete();

    $assignment->refresh();
    expect($assignment->exists)->toBeTrue();
    expect($assignment->organization_id)->toBeNull();
});

test('an adviser freed by an organization delete reappears as available in the adviser search', function () {
    $org = Organization::factory()->create();
    $adviser = User::factory()->create(['name' => 'Freed Adviser Jones']);
    RoleAssignment::factory()->create([
        'user_id' => $adviser->id,
        'role' => Role::Adviser,
        'organization_id' => $org->id,
    ]);

    $org->delete();

    $student = User::factory()->create();
    $response = $this->actingAs($student)->getJson(route('registrations.adviser-search', ['q' => 'Freed Adviser']));

    $response->assertOk();
    $advisers = $response->json('advisers');
    expect($advisers)->toHaveCount(1);
    expect($advisers[0]['id'])->toBe($adviser->id);
    expect($advisers[0]['is_available'])->toBeTrue();
});
