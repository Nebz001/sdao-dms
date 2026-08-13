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
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
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

test('a return-for-revision shares a normalized toast on the redirected page', function () {
    $doc = flashTestRegistration($this->org, $this->engine, $this->studentAlpha);

    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->post(route('review.registrations.return', $doc), ['comment' => 'Please fix the contact info.'])
        ->assertRedirect(route('review.registrations.show', $doc));

    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route('review.registrations.show', $doc))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('flash.toast.type', 'success')
            ->where('flash.toast.message', 'Document returned for revision.')
        );
});

test('the quorum-completing approval shares a success toast on the queue page', function () {
    $doc = flashTestRegistration($this->org, $this->engine, $this->studentAlpha);

    // First SDAO member approves — document stays In Review, one seat left.
    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->post(route('review.registrations.approve', $doc))
        ->assertRedirect(route('review.registrations.show', $doc));

    // Second SDAO member's approval completes the two-approver quorum
    // (WorkflowTemplateSeeder's short chain: [Role::SdaoMember, 2]) and the
    // document has no current step left, so the controller sends the
    // approver to the queue instead of the (now-403-for-them) show page.
    $this->actingAs($this->sdaoB)
        ->withoutVite()
        ->post(route('review.registrations.approve', $doc))
        ->assertRedirect(route('review.registrations.index'));

    $this->actingAs($this->sdaoB)
        ->withoutVite()
        ->get(route('review.registrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('flash.toast.type', 'success')
            ->where('flash.toast.message', 'Registration approved.')
        );
});

test('a partial reload landing between the redirect and its target does not consume the flash', function () {
    $doc = flashTestRegistration($this->org, $this->engine, $this->studentAlpha);

    // An Inertia asset-version mismatch forces a 409 + full-page reload, so
    // the partial request further down must echo back the server's current
    // version or it never reaches the flash-consuming code path we're
    // testing. Computed the same way Inertia\Middleware::version() does
    // rather than fetched over HTTP, so this doesn't touch the session flash
    // we're about to set with the reject below.
    $version = hash_file('xxh128', public_path('build/manifest.json'));

    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->post(route('review.registrations.reject', $doc), ['comment' => 'Incomplete documentation.'])
        ->assertRedirect(route('review.registrations.index'));

    // Simulates useDocumentUpdates()' 5-second async poll
    // (resources/js/hooks/use-document-updates.ts:28) landing on the queue
    // page before the browser's own follow-up GET of the redirect target.
    // router.reload({ only: [...] }) sends these exact Inertia partial-reload
    // headers; HandleInertiaRequests::share() reflashes the session on
    // partial requests so this doesn't burn the toast before the page it
    // belongs to actually loads (see the comment there).
    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route('review.registrations.index'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Data' => 'queue',
            'X-Inertia-Partial-Component' => 'review/registrations/index',
        ])
        ->assertOk();

    // The real navigation the browser performs right after the poll — this is
    // where the toast was actually supposed to fire, and now does.
    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route('review.registrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('flash.toast.type', 'success')
            ->where('flash.toast.message', 'Registration rejected.')
        );
});
