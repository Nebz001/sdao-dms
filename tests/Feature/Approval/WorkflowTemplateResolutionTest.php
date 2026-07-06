<?php

use App\Approval\StepApproverResolver;
use App\Approval\WorkflowTemplateResolver;
use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Organization;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class]);
    $this->resolver = app(WorkflowTemplateResolver::class);
    $this->approverResolver = app(StepApproverResolver::class);
});

// ── Tests 1–4: Template step order ───────────────────────────────────────────

test('regular on-calendar resolves 7 steps in correct order', function () {
    $template = $this->resolver->resolve(FormType::ActivityProposal, ProposalVariant::RegularOnCalendar);
    $roles = $template->steps->pluck('role');

    expect($roles)->sequence(
        fn ($r) => $r->toBe(Role::Adviser),
        fn ($r) => $r->toBe(Role::ProgramChair),
        fn ($r) => $r->toBe(Role::Dean),
        fn ($r) => $r->toBe(Role::SdaoMember),
        fn ($r) => $r->toBe(Role::AssistantDirectorAcademicServices),
        fn ($r) => $r->toBe(Role::AcademicDirector),
        fn ($r) => $r->toBe(Role::ExecutiveDirector),
    );
    expect($template->steps)->toHaveCount(7);
});

test('regular off-calendar has SDAO at position 1', function () {
    $template = $this->resolver->resolve(FormType::ActivityProposal, ProposalVariant::RegularOffCalendar);
    $steps = $template->steps;

    expect($steps->first()->role)->toBe(Role::SdaoMember);
    expect($steps->first()->position)->toBe(1);

    $roles = $steps->pluck('role');
    expect($roles)->sequence(
        fn ($r) => $r->toBe(Role::SdaoMember),
        fn ($r) => $r->toBe(Role::Adviser),
        fn ($r) => $r->toBe(Role::ProgramChair),
        fn ($r) => $r->toBe(Role::Dean),
        fn ($r) => $r->toBe(Role::AssistantDirectorAcademicServices),
        fn ($r) => $r->toBe(Role::AcademicDirector),
        fn ($r) => $r->toBe(Role::ExecutiveDirector),
    );
});

test('SHS on-calendar has adviser, principal, SDAO, then 3 directors — no chair or dean', function () {
    $template = $this->resolver->resolve(FormType::ActivityProposal, ProposalVariant::ShsOnCalendar);
    $roles = $template->steps->pluck('role');

    expect($roles)->sequence(
        fn ($r) => $r->toBe(Role::Adviser),
        fn ($r) => $r->toBe(Role::Principal),
        fn ($r) => $r->toBe(Role::SdaoMember),
        fn ($r) => $r->toBe(Role::AssistantDirectorAcademicServices),
        fn ($r) => $r->toBe(Role::AcademicDirector),
        fn ($r) => $r->toBe(Role::ExecutiveDirector),
    );
    expect($template->steps)->toHaveCount(6);
    expect($roles->contains(Role::ProgramChair))->toBeFalse();
    expect($roles->contains(Role::Dean))->toBeFalse();
});

test('SHS off-calendar has SDAO at front and principal in place of chair and dean', function () {
    $template = $this->resolver->resolve(FormType::ActivityProposal, ProposalVariant::ShsOffCalendar);
    $roles = $template->steps->pluck('role');

    expect($roles)->sequence(
        fn ($r) => $r->toBe(Role::SdaoMember),
        fn ($r) => $r->toBe(Role::Adviser),
        fn ($r) => $r->toBe(Role::Principal),
        fn ($r) => $r->toBe(Role::AssistantDirectorAcademicServices),
        fn ($r) => $r->toBe(Role::AcademicDirector),
        fn ($r) => $r->toBe(Role::ExecutiveDirector),
    );
});

// ── Tests 5–6: Role→person resolution per org context ────────────────────────

test('step roles resolve to the correct seeded person for a regular-school org', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $template = $this->resolver->resolve(FormType::ActivityProposal, ProposalVariant::RegularOnCalendar);

    $doc = Document::factory()->create([
        'organization_id' => $org->id,
        'workflow_template_id' => $template->id,
    ]);

    $byRole = $template->steps->keyBy(fn ($s) => $s->role->value);

    expect($this->approverResolver->approversFor($byRole[Role::Adviser->value], $doc)->first()->name)
        ->toBe('Adviser One');
    expect($this->approverResolver->approversFor($byRole[Role::ProgramChair->value], $doc)->first()->name)
        ->toBe('Chair CS');
    expect($this->approverResolver->approversFor($byRole[Role::Dean->value], $doc)->first()->name)
        ->toBe('Dean CCIT');
    expect($this->approverResolver->approversFor($byRole[Role::AssistantDirectorAcademicServices->value], $doc)->first()->name)
        ->toBe('Asst. Director of Academic Services');
    expect($this->approverResolver->approversFor($byRole[Role::AcademicDirector->value], $doc)->first()->name)
        ->toBe('Academic Director');
    expect($this->approverResolver->approversFor($byRole[Role::ExecutiveDirector->value], $doc)->first()->name)
        ->toBe('Executive Director');
});

test('SDAO step resolves to exactly two members with required_approvals of 2', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $template = $this->resolver->resolve(FormType::ActivityProposal, ProposalVariant::RegularOnCalendar);

    $doc = Document::factory()->create([
        'organization_id' => $org->id,
        'workflow_template_id' => $template->id,
    ]);

    $sdaoStep = $template->steps->firstWhere('role', Role::SdaoMember);

    expect($sdaoStep->required_approvals)->toBe(2);

    $members = $this->approverResolver->approversFor($sdaoStep, $doc);
    expect($members)->toHaveCount(2);
    expect($members->pluck('name')->sort()->values()->toArray())->toBe(['SDAO Member A', 'SDAO Member B']);
});
