<?php

use App\Approval\ApprovalEngine;
use App\Approval\StepApproverResolver;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Enums\Role;
use App\Identity\Admin\ProvisionApprover;
use App\Models\Document;
use App\Models\DocumentStepApproval;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Models\WorkflowStep;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Replacing an SDAO member: SDAO is multi-holder, so provisioning alone only
 * adds a seat. `replaces_user_id` removes the outgoing member's role row and
 * nothing else, which keeps the append-only history intact.
 */
beforeEach(function () {
    Notification::fake();
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->oldSdao = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->otherSdao = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
});

function driveProposalToSdaoStep(ApprovalEngine $engine, Organization $org): Document
{
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $chair = User::where('email', 'chair-cs@nu-lipa.edu.ph')->firstOrFail();
    $dean = User::where('email', 'dean-ccit@nu-lipa.edu.ph')->firstOrFail();

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
    $engine->approve($doc, $dean);

    return $doc->refresh();
}

function replaceOldSdaoWith(User $actor, User $old, string $email = 'magpantaycarljustin@gmail.com'): User
{
    return app(ProvisionApprover::class)->execute(
        actor: $actor,
        name: 'Carl Justin Magpantay',
        email: $email,
        role: Role::SdaoMember,
        scope: [],
        replacesUserId: $old->id,
    );
}

test('the replaced SDAO member loses the role but keeps their account', function () {
    $new = replaceOldSdaoWith($this->otherSdao, $this->oldSdao);

    expect(RoleAssignment::where('user_id', $this->oldSdao->id)->where('role', Role::SdaoMember->value)->exists())->toBeFalse()
        ->and(RoleAssignment::where('user_id', $new->id)->where('role', Role::SdaoMember->value)->exists())->toBeTrue()
        ->and(User::whereKey($this->oldSdao->id)->exists())->toBeTrue()
        ->and(RoleAssignment::where('role', Role::SdaoMember->value)->count())->toBe(2);
});

test('the append-only history still names the replaced member', function () {
    $doc = driveProposalToSdaoStep($this->engine, $this->org);
    $this->engine->approve($doc, $this->oldSdao);

    $transitions = DocumentTransition::where('actor_id', $this->oldSdao->id)->count();
    $approvals = DocumentStepApproval::where('user_id', $this->oldSdao->id)->count();
    $allTransitions = DocumentTransition::count();
    $allApprovals = DocumentStepApproval::count();

    expect($transitions)->toBeGreaterThan(0)->and($approvals)->toBe(1);

    replaceOldSdaoWith($this->otherSdao, $this->oldSdao);

    expect(DocumentTransition::where('actor_id', $this->oldSdao->id)->count())->toBe($transitions)
        ->and(DocumentStepApproval::where('user_id', $this->oldSdao->id)->count())->toBe($approvals)
        ->and(DocumentTransition::count())->toBe($allTransitions)
        ->and(DocumentStepApproval::count())->toBe($allApprovals);
});

test('a document pending at the SDAO step now resolves to the new holder, not the old one', function () {
    $doc = driveProposalToSdaoStep($this->engine, $this->org);
    expect($doc->current_step_position)->toBe(4);

    $new = replaceOldSdaoWith($this->otherSdao, $this->oldSdao);

    $step = WorkflowStep::query()
        ->where('workflow_template_id', $doc->workflow_template_id)
        ->where('position', 4)
        ->firstOrFail();

    $approverIds = app(StepApproverResolver::class)->approversFor($step, $doc)->pluck('id')->all();

    expect($approverIds)->toContain($new->id)
        ->and($approverIds)->toContain($this->otherSdao->id)
        ->and($approverIds)->not->toContain($this->oldSdao->id);

    $this->actingAs($new)->withoutVite()
        ->get(route('review.activity-proposals.show', $doc))
        ->assertOk();

    $this->actingAs($this->oldSdao->fresh())->withoutVite()
        ->post(route('review.activity-proposals.approve', $doc))
        ->assertForbidden();

    // Dual approval still needs two current holders: the new member plus the other one.
    $this->engine->approve($doc, $new);
    $this->engine->approve($doc->refresh(), $this->otherSdao);

    expect($doc->refresh()->current_step_position)->toBe(5);
});

test('an approval the old member already cast before being replaced still counts toward the quorum', function () {
    $doc = driveProposalToSdaoStep($this->engine, $this->org);
    $this->engine->approve($doc, $this->oldSdao);

    $new = replaceOldSdaoWith($this->otherSdao, $this->oldSdao);

    $this->engine->approve($doc->refresh(), $new);

    expect($doc->refresh()->current_step_position)->toBe(5);
});

test('an SDAO admin can replace a member through the form endpoint and sees the confirmation flash', function () {
    $this->actingAs($this->otherSdao)->post(route('admin.approvers.store'), [
        'name' => 'Carl Justin Magpantay',
        'email' => 'Magpantaycarljustin@gmail.com',
        'role' => Role::SdaoMember->value,
        'replaces_user_id' => $this->oldSdao->id,
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.approvers.index'))
        ->assertSessionHas('flash.message', fn (string $m) => str_contains($m, $this->oldSdao->name));

    expect(RoleAssignment::where('user_id', $this->oldSdao->id)->where('role', Role::SdaoMember->value)->exists())->toBeFalse();
});

test('replaces_user_id is rejected for a role that is not SDAO member', function () {
    $this->actingAs($this->otherSdao)->post(route('admin.approvers.store'), [
        'name' => 'Some Adviser',
        'email' => 'some-adviser@gmail.com',
        'role' => Role::Adviser->value,
        'replaces_user_id' => $this->oldSdao->id,
    ])->assertSessionHasErrors('replaces_user_id');

    expect(User::where('email', 'some-adviser@gmail.com')->exists())->toBeFalse()
        ->and(RoleAssignment::where('user_id', $this->oldSdao->id)->where('role', Role::SdaoMember->value)->exists())->toBeTrue();
});

test('replaces_user_id must point at a current SDAO member', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    $this->actingAs($this->otherSdao)->post(route('admin.approvers.store'), [
        'name' => 'Carl Justin Magpantay',
        'email' => 'magpantaycarljustin@gmail.com',
        'role' => Role::SdaoMember->value,
        'replaces_user_id' => $adviser->id,
    ])->assertSessionHasErrors('replaces_user_id');
});

test('an SDAO member cannot replace themselves', function () {
    expect(fn () => replaceOldSdaoWith($this->oldSdao, $this->oldSdao))->toThrow(ValidationException::class);

    expect(RoleAssignment::where('user_id', $this->oldSdao->id)->where('role', Role::SdaoMember->value)->exists())->toBeTrue()
        ->and(User::where('email', 'magpantaycarljustin@gmail.com')->exists())->toBeFalse();
});
