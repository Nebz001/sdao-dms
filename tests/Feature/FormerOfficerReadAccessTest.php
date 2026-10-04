<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use App\Organizations\OrganizationMembershipService;
use App\Support\NavCounts;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Gate;

/**
 * READ-side twin of FormerOfficerSubmitterAccessTest. A removed officer's
 * access follows the roster for reading exactly as it does for acting: the
 * student-side Submitted/Resubmitted transitions carry their actor_id, and
 * DocumentPolicy::hasActedOn() must NOT treat those as "has acted on it".
 * Approver-side acts (approve/return/reject) still grant persistent read
 * access — that separation is the whole point of hasActedOn().
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
    $this->president = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->secretary = User::where('email', 'student-delta@students.nu-lipa.edu.ph')->firstOrFail();
});

function submittedRegistrationFor(Organization $org, ApprovalEngine $engine, User $submitter): Document
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

    return $doc->refresh();
}

function closeSeatOf(User $user): void
{
    app(OrganizationMembershipService::class)->close(
        OrganizationMembership::query()->where('user_id', $user->id)->active()->firstOrFail(),
    );
}

test('a removed officer can no longer view a document they submitted, through the policy or the page', function () {
    $doc = submittedRegistrationFor($this->org, $this->engine, $this->president);
    closeSeatOf($this->president);
    $former = $this->president->fresh();

    expect(Gate::forUser($former)->allows('view', $doc))->toBeFalse();
    expect(Gate::forUser($former)->allows('reviewView', $doc))->toBeFalse();
    $this->actingAs($former)->withoutVite()->get(route('registrations.show', $doc))->assertForbidden();

    // The remaining officer is unaffected.
    expect(Gate::forUser($this->secretary)->allows('view', $doc))->toBeTrue();
    $this->actingAs($this->secretary)->withoutVite()->get(route('registrations.show', $doc))->assertOk();
});

test('a removed officer who RESUBMITTED a returned document loses read access too', function () {
    $doc = submittedRegistrationFor($this->org, $this->engine, $this->secretary);
    $this->engine->returnForRevision($doc, $this->sdaoA, 'Fix it.');
    $doc->refresh();
    $this->engine->resubmit($doc, $this->secretary);
    $doc->refresh();

    closeSeatOf($this->secretary);

    expect(Gate::forUser($this->secretary->fresh())->allows('view', $doc))->toBeFalse();
    expect(Gate::forUser($this->president)->allows('view', $doc))->toBeTrue();
});

test('the loss of read access holds in every document state, including terminal ones', function (DocumentStatus $status) {
    $doc = submittedRegistrationFor($this->org, $this->engine, $this->president);
    $doc->update(['status' => $status]);
    closeSeatOf($this->president);

    expect(Gate::forUser($this->president->fresh())->allows('view', $doc->refresh()))->toBeFalse();
})->with([DocumentStatus::InReview, DocumentStatus::Returned, DocumentStatus::Approved, DocumentStatus::Rejected]);

test('an approver who acted keeps persistent read access — only student-side transitions stopped counting', function () {
    $doc = submittedRegistrationFor($this->org, $this->engine, $this->president);
    $this->engine->returnForRevision($doc, $this->sdaoA, 'Fix it.');
    $doc->refresh();

    expect(Gate::forUser($this->sdaoA)->allows('view', $doc))->toBeTrue();
    expect(Gate::forUser($this->sdaoA)->allows('reviewView', $doc))->toBeTrue();
    // The OTHER SDAO member never clicked anything but is a reached-step approver.
    expect(Gate::forUser($this->sdaoB)->allows('view', $doc))->toBeTrue();
});

test('hasActedOn answers identically whether or not the transitions relation is preloaded', function () {
    $doc = submittedRegistrationFor($this->org, $this->engine, $this->president);
    closeSeatOf($this->president);
    $former = $this->president->fresh();

    $queried = Gate::forUser($former)->allows('view', Document::find($doc->id));
    $loaded = Gate::forUser($former)->allows('view', Document::with(['transitions', 'stepApprovals'])->find($doc->id));

    expect($queried)->toBeFalse()->and($loaded)->toBeFalse();
});

test('a founding student with no officers yet can still view their own pending registration', function () {
    $founder = User::factory()->create();
    $org = Organization::factory()->create();
    $doc = submittedRegistrationFor($org, $this->engine, $founder);

    expect(app(OrganizationMembershipService::class)->hasActiveOfficers($org))->toBeFalse();
    expect(Gate::forUser($founder)->allows('view', $doc))->toBeTrue();
});

test('Document History and the nav counts agree with the view gate for a removed officer', function () {
    $doc = submittedRegistrationFor($this->org, $this->engine, $this->president);

    expect(app(NavCounts::class)->for($this->president)['documents']['history'])->toBeGreaterThanOrEqual(1);

    closeSeatOf($this->president);
    $former = $this->president->fresh();

    expect(app(NavCounts::class)->for($former)['documents'])->each->toBe(0);
    $this->actingAs($former)->withoutVite()->get(route('document-history.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('documents.data', fn ($rows) => collect($rows)->doesntContain('id', $doc->id)));

    expect(app(NavCounts::class)->for($this->secretary)['documents']['history'])->toBeGreaterThanOrEqual(1);
});
