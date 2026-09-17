<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Enums\Role;
use App\Identity\Admin\ProvisionApprover;
use App\Models\Document;
use App\Models\Organization;
use App\Models\School;
use App\Models\User;
use App\Notifications\ApproverHandOffNotification;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Regression coverage for the reported bug: clicking an approver
 * notification on the admin side led to a persistent 403 on the review page
 * that never resolved on retry. Root cause — Admin\ProvisionApprover
 * previously only retired the incumbent's role_assignments row for GLOBAL
 * single-holder roles; a scope-bound role (Dean here) fell into a plain
 * create(), leaving two live rows for the same (role, school_id).
 * RoleDirectory::resolveSchoolScoped() then had no explicit ordering, so
 * which row "won" was undefined — and ApprovalEngine::activateStep() (who
 * gets notified) and DocumentPolicy::review()/isChainApprover() (who is
 * authorized) could resolve that ambiguity differently.
 *
 * Deliberately NOT the "original holder's notification goes stale"
 * direction: under sqlite's typical unordered scan order, an unordered
 * firstOrFail() already happens to return the lowest-id (incumbent) row
 * both before and after this fix, so that direction would pass either way
 * and prove nothing. The reliably-reproducible, deterministic pre-fix
 * failure is the opposite direction — the REPLACEMENT approver, the one
 * SDAO just provisioned, never becomes the effective dean at all: the stale
 * incumbent row keeps winning, so the replacement is neither notified nor
 * authorized to open the very document their notification would have
 * pointed at, had they received one. That is the same underlying defect,
 * just its deterministic manifestation — see RoleResolutionTest.php's
 * "Duplicate scoped-role assignments" section for the direction pinned
 * there instead (first-assigned wins, which is NOT sqlite-order-dependent
 * since both rows are inserted directly with a known id order).
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->school = School::where('name', 'School of Computing and IT')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->chair = User::where('email', 'chair-cs@nu-lipa.edu.ph')->firstOrFail();
    $this->incumbentDean = User::where('email', 'dean-ccit@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
});

function driveActivityProposalToDeanStep(ApprovalEngine $engine, Organization $org, User $adviser, User $chair): Document
{
    $doc = Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'variant' => ProposalVariant::RegularOnCalendar,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Draft,
    ]);

    $engine->submit($doc, $adviser);
    $doc->refresh();
    $engine->approve($doc, $adviser);
    $engine->approve($doc, $chair);
    $doc->refresh();

    return $doc;
}

test('a dean provisioned as a replacement receives the hand-off notification and can open the review page without a 403', function () {
    $replacement = app(ProvisionApprover::class)->execute(
        actor: $this->sdaoA,
        name: 'Replacement Dean',
        email: 'replacement-dean@nu-lipa.edu.ph',
        role: Role::Dean,
        scope: ['school_id' => $this->school->id],
    );

    $doc = driveActivityProposalToDeanStep($this->engine, $this->org, $this->adviser, $this->chair);
    expect($doc->status)->toBe(DocumentStatus::InReview);
    expect($doc->current_step_position)->toBe(3); // Dean step

    // Filtered by type, not just first() — provisioning itself already sent
    // this user an ApproverProvisionedNotification, so the two can tie on
    // created_at within the same test.
    $notification = $replacement->notifications
        ->firstWhere('type', ApproverHandOffNotification::class);

    expect($notification)->not->toBeNull()
        ->and($notification->data['kind'])->toBe('approver_hand_off')
        ->and($notification->data['document_id'])->toBe($doc->id);

    $url = $notification->data['url'];
    expect($url)->toBe(route('review.activity-proposals.show', $doc, absolute: false));

    // The literal click-the-bell-link path that produced the reported 403.
    $this->actingAs($replacement)->withoutVite()->get($url)->assertOk();

    // Not merely readable — DocumentPolicy::review() (the ability the
    // action endpoints gate on) must agree too, so hand-off and click-time
    // authorization can never disagree.
    $this->actingAs($replacement)
        ->withoutVite()
        ->post(route('review.activity-proposals.approve', $doc))
        ->assertRedirect(route('review.activity-proposals.show', $doc));

    $doc->refresh();
    expect($doc->current_step_position)->toBe(4); // advanced to the SDAO step
});

test('the superseded dean, who never acted on the document, is neither notified nor authorized to open it', function () {
    app(ProvisionApprover::class)->execute(
        actor: $this->sdaoA,
        name: 'Replacement Dean',
        email: 'replacement-dean@nu-lipa.edu.ph',
        role: Role::Dean,
        scope: ['school_id' => $this->school->id],
    );

    $doc = driveActivityProposalToDeanStep($this->engine, $this->org, $this->adviser, $this->chair);
    expect($doc->current_step_position)->toBe(3);

    expect($this->incumbentDean->notifications()->count())->toBe(0);

    $this->actingAs($this->incumbentDean)
        ->withoutVite()
        ->get(route('review.activity-proposals.show', $doc))
        ->assertForbidden();
});
