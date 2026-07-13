<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
});

/** Create a submitted (InReview) registration for Computing Society. */
function flashTestRegistration(Organization $org, ApprovalEngine $engine, User $submitter): Document
{
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $submitter->id,
    ]);
    OrganizationRegistrationDetail::factory()->create([
        'document_id' => $doc->id,
        'organization_type' => OrganizationType::CoCurricular,
    ]);
    $engine->submit($doc, $submitter);
    $doc->refresh();

    return $doc;
}

test('an SDAO approval shares a normalized success toast on the redirected page', function () {
    $doc = flashTestRegistration($this->org, $this->engine, $this->studentAlpha);

    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->post(route('review.registrations.approve', $doc))
        ->assertRedirect(route('review.registrations.show', $doc));

    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route('review.registrations.show', $doc))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('flash.toast.type', 'success')
            ->where('flash.toast.message', 'Approval recorded.')
        );
});

test('a rejection shares a normalized toast and the flash does not leak to the next unrelated page', function () {
    $doc = flashTestRegistration($this->org, $this->engine, $this->studentAlpha);

    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->post(route('review.registrations.reject', $doc), ['comment' => 'Incomplete documentation.'])
        ->assertRedirect(route('review.registrations.index'));

    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route('review.registrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('flash.toast.type', 'success')
            ->where('flash.toast.message', 'Registration rejected.')
        );

    // Session flash is one-request-only — a second, unrelated visit must not
    // still see the same toast fire again.
    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route('review.registrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('flash', null));
});
