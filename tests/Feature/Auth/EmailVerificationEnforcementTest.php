<?php

use App\Enums\FormType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
});

test('an unverified user is redirected away from the dashboard, not served', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('verification.notice'));
});

test('a verified user reaches the dashboard normally', function () {
    $user = User::factory()->create(); // factory default: email_verified_at = now()

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
});

test('an unverified officer cannot submit a document — blocked before the action ever runs', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $officer = User::factory()->unverified()->create();
    OrganizationMembership::create([
        'user_id' => $officer->id,
        'organization_id' => $org->id,
        'position' => 'president',
        'academic_year' => '2026-2027',
        'is_active' => true,
    ]);

    $response = $this->actingAs($officer)->post(route('registrations.store'), [
        'organization_type' => 'co_curricular',
        'purpose_of_organization' => 'Should never be created.',
        'contact_person' => 'Someone',
        'contact_no' => '09170000000',
        'email_address' => 'someone@example.test',
        'date_organized' => '2020-06-01',
    ]);

    $response->assertRedirect(route('verification.notice'));
    expect(Document::where('form_type', FormType::OrganizationRegistration->value)
        ->where('organization_id', $org->id)
        ->exists())->toBeFalse();
});
