<?php

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * ActivityProposalController::partnerOrganizationSearch() — mirrors
 * JoinOrganizationController::search()'s no-Gate, public-names contract, plus
 * a self-exclusion rule this endpoint adds: the caller's own active-membership
 * organization never appears in its own partner-search results, since it's
 * already shown separately on the same form as "Name of RSO" and would never
 * be a meaningful partner suggestion.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->computingSociety = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail(); // president, Computing Society
});

test('the route is reachable and not swallowed by the {document} wildcard route', function () {
    $response = $this->actingAs($this->studentAlpha)
        ->getJson(route('activity-proposals.partner-organization-search', ['q' => 'Society']));

    // A 404/redirect here would mean /activity-proposals/{document} matched
    // "partner-organization-search" as a document id string instead.
    $response->assertOk();
    $response->assertJsonStructure(['organizations']);
});

test('search returns matching organizations with their school and program names', function () {
    $itGuild = Organization::where('name', 'IT Guild')->firstOrFail();

    $response = $this->actingAs($this->studentAlpha)
        ->getJson(route('activity-proposals.partner-organization-search', ['q' => 'IT Guild']));

    $response->assertOk();
    $organizations = collect($response->json('organizations'));
    expect($organizations->pluck('id'))->toContain($itGuild->id);

    $match = $organizations->firstWhere('id', $itGuild->id);
    expect($match['name'])->toBe('IT Guild');
    expect($match['school'])->not->toBeNull();
});

test('search excludes the caller\'s own active-membership organization', function () {
    // student-alpha's own org is Computing Society — searching a term that
    // matches it must never suggest it back as a "partner".
    $response = $this->actingAs($this->studentAlpha)
        ->getJson(route('activity-proposals.partner-organization-search', ['q' => 'Computing Society']));

    $response->assertOk();
    expect(collect($response->json('organizations'))->pluck('id'))
        ->not->toContain($this->computingSociety->id);
});

test('a caller with no active membership sees every matching organization, including their own if any', function () {
    $noMembership = User::factory()->create();

    $response = $this->actingAs($noMembership)
        ->getJson(route('activity-proposals.partner-organization-search', ['q' => 'Computing Society']));

    $response->assertOk();
    expect(collect($response->json('organizations'))->pluck('id'))
        ->toContain($this->computingSociety->id);
});

test('search caps results at the documented limit', function () {
    for ($i = 0; $i < 15; $i++) {
        Organization::factory()->create(['name' => "Bulk Search Org {$i}"]);
    }

    $response = $this->actingAs($this->studentAlpha)
        ->getJson(route('activity-proposals.partner-organization-search', ['q' => 'Bulk Search Org']));

    $response->assertOk();
    expect(collect($response->json('organizations')))->toHaveCount(10);
});

test('an empty q returns unfiltered results, same as the join-organization search', function () {
    $response = $this->actingAs($this->studentAlpha)
        ->getJson(route('activity-proposals.partner-organization-search'));

    $response->assertOk();
    expect(collect($response->json('organizations')))->not->toBeEmpty();
});
