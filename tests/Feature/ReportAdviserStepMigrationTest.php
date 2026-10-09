<?php

use App\Approval\WorkflowTemplateResolver;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\Role;
use App\Enums\TransitionAction;
use App\Models\ApprovalNotification;
use App\Models\Document;
use App\Models\DocumentStepApproval;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Models\WorkflowTemplate;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/*
 * The data migration that puts the adviser in front of SDAO on the
 * After-Activity Report chain. Each test first rewinds the seeded templates to
 * the old single-SDAO-step shape, builds reports in every state, then runs the
 * migration's up() against that "production" data.
 */

beforeEach(function () {
    Notification::fake();
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);

    // Rewind to the old chain: SDAO is the only step, at position 1.
    $this->legacy = WorkflowTemplate::where('form_type', FormType::AfterActivityReport->value)->firstOrFail();
    WorkflowStep::where('workflow_template_id', $this->legacy->id)->where('position', 1)->delete();
    WorkflowStep::where('workflow_template_id', $this->legacy->id)->where('position', 2)->update(['position' => 1]);
    $this->legacySdaoStep = WorkflowStep::where('workflow_template_id', $this->legacy->id)->firstOrFail();

    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
});

function legacyReport(Organization $org, User $student, DocumentStatus $status, ?int $position, string $title): Document
{
    $legacy = WorkflowTemplate::where('form_type', FormType::AfterActivityReport->value)->firstOrFail();

    $document = Document::create([
        'form_type' => FormType::AfterActivityReport,
        'variant' => null,
        'title' => $title,
        'status' => $status,
        'current_step_position' => $position,
        'organization_id' => $org->id,
        'workflow_template_id' => $legacy->id,
        'submitted_by' => $student->id,
    ]);

    DocumentTransition::create([
        'document_id' => $document->id,
        'actor_id' => $student->id,
        'action' => TransitionAction::Submitted,
        'from_status' => DocumentStatus::Draft,
        'to_status' => DocumentStatus::InReview,
        'step_position' => 1,
        'created_at' => now(),
    ]);

    return $document;
}

function legacyDecision(Document $document, User $actor, TransitionAction $action, DocumentStatus $to): void
{
    DocumentTransition::create([
        'document_id' => $document->id,
        'actor_id' => $actor->id,
        'action' => $action,
        'from_status' => DocumentStatus::InReview,
        'to_status' => $to,
        'step_position' => 1,
        'created_at' => now(),
    ]);
}

function runAdviserStepMigration(): void
{
    (require database_path('migrations/2026_10_09_100001_add_adviser_step_to_after_activity_report_workflow.php'))->up();
}

/** @return array<int, array<string, mixed>> */
function transitionSnapshot(): array
{
    return DocumentTransition::orderBy('id')->get()->map->getAttributes()->all();
}

test('new submissions resolve to the Adviser then SDAO template and the old one is retired, not edited', function () {
    runAdviserStepMigration();

    $active = app(WorkflowTemplateResolver::class)->resolve(FormType::AfterActivityReport);

    expect($active->id)->not->toBe($this->legacy->id)
        ->and($active->steps->map(fn ($s) => [$s->position, $s->role->value, $s->required_approvals])->all())
        ->toBe([[1, 'adviser', 1], [2, 'sdao_member', 2]]);

    $this->legacy->refresh();
    expect($this->legacy->retired_at)->not->toBeNull()
        ->and($this->legacy->steps->map(fn ($s) => [$s->position, $s->role->value, $s->required_approvals])->all())
        ->toBe([[1, 'sdao_member', 2]]);
});

test('a report no approver has acted on moves to the adviser step, and the adviser is notified', function () {
    $waiting = legacyReport($this->org, $this->student, DocumentStatus::InReview, 1, 'Waiting at SDAO');

    runAdviserStepMigration();

    $waiting->refresh();
    $active = app(WorkflowTemplateResolver::class)->resolve(FormType::AfterActivityReport);

    expect($waiting->workflow_template_id)->toBe($active->id)
        ->and($waiting->current_step_position)->toBe(1)
        ->and($waiting->status)->toBe(DocumentStatus::InReview);

    expect(ApprovalNotification::where('document_id', $waiting->id)->where('user_id', $this->adviser->id)->exists())->toBeTrue()
        ->and(ApprovalNotification::where('document_id', $waiting->id)->where('user_id', $this->sdaoA->id)->exists())->toBeFalse();

    // The adviser can now act on it through the normal policy and engine.
    $this->actingAs($this->adviser)->withoutVite()
        ->post(route('review.reports.approve', $waiting))
        ->assertRedirect();
    expect($waiting->refresh()->current_step_position)->toBe(2);
});

test('finished, returned and SDAO-started reports keep the old route untouched', function () {
    $approved = legacyReport($this->org, $this->student, DocumentStatus::Approved, null, 'Finished approved');
    legacyDecision($approved, $this->sdaoA, TransitionAction::Completed, DocumentStatus::Approved);

    $rejected = legacyReport($this->org, $this->student, DocumentStatus::Rejected, null, 'Finished rejected');
    legacyDecision($rejected, $this->sdaoA, TransitionAction::Rejected, DocumentStatus::Rejected);

    $returned = legacyReport($this->org, $this->student, DocumentStatus::Returned, 1, 'Returned by SDAO');
    legacyDecision($returned, $this->sdaoA, TransitionAction::Returned, DocumentStatus::Returned);

    $started = legacyReport($this->org, $this->student, DocumentStatus::InReview, 1, 'SDAO half-approved');
    DocumentStepApproval::create([
        'document_id' => $started->id,
        'workflow_step_id' => $this->legacySdaoStep->id,
        'step_position' => 1,
        'user_id' => $this->sdaoA->id,
    ]);
    legacyDecision($started, $this->sdaoA, TransitionAction::Approved, DocumentStatus::InReview);

    $before = transitionSnapshot();

    runAdviserStepMigration();

    foreach ([$approved, $rejected, $returned, $started] as $document) {
        $fresh = $document->fresh();

        expect($fresh->workflow_template_id)->toBe($this->legacy->id)
            ->and($fresh->status)->toBe($document->status)
            ->and($fresh->current_step_position)->toBe($document->current_step_position);
    }

    // SDAO's partial approval survives and still reads against the old step.
    expect(DocumentStepApproval::where('document_id', $started->id)->count())->toBe(1);

    // The log is append only: nothing was edited or deleted.
    expect(transitionSnapshot())->toBe($before);
});

test('a report whose organization has no active adviser stays on the old route instead of getting stuck', function () {
    $itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $itStudent = User::where('email', 'student-beta@students.nu-lipa.edu.ph')->firstOrFail();
    User::where('email', 'adviser-two@nu-lipa.edu.ph')->firstOrFail()->forceFill(['deactivated_at' => now()])->save();

    $stuckOtherwise = legacyReport($itGuild, $itStudent, DocumentStatus::InReview, 1, 'No adviser to receive it');
    $movable = legacyReport($this->org, $this->student, DocumentStatus::InReview, 1, 'Has an adviser');

    runAdviserStepMigration();

    expect($stuckOtherwise->fresh()->workflow_template_id)->toBe($this->legacy->id)
        ->and($movable->fresh()->workflow_template_id)->not->toBe($this->legacy->id);
});

test('running the migration twice changes nothing the second time', function () {
    legacyReport($this->org, $this->student, DocumentStatus::InReview, 1, 'Waiting at SDAO');

    runAdviserStepMigration();
    $templates = WorkflowTemplate::count();
    $steps = WorkflowStep::count();
    $notifications = ApprovalNotification::count();

    runAdviserStepMigration();

    expect(WorkflowTemplate::count())->toBe($templates)
        ->and(WorkflowStep::count())->toBe($steps)
        ->and(ApprovalNotification::count())->toBe($notifications);
});

test('rerunning the seeder after the migration keeps one active report template with the new chain', function () {
    runAdviserStepMigration();

    $this->seed(WorkflowTemplateSeeder::class);

    $active = WorkflowTemplate::active()->where('form_type', FormType::AfterActivityReport->value)->get();

    expect($active)->toHaveCount(1)
        ->and($active->first()->steps->pluck('role.value')->all())->toBe([Role::Adviser->value, Role::SdaoMember->value])
        ->and(WorkflowTemplate::where('form_type', FormType::AfterActivityReport->value)->count())->toBe(2);
});

test('a duplicated or unexpected report template fails loudly and changes nothing', function () {
    $duplicate = WorkflowTemplate::create(['form_type' => FormType::AfterActivityReport, 'variant' => null, 'name' => 'Duplicate']);
    $waiting = legacyReport($this->org, $this->student, DocumentStatus::InReview, 1, 'Waiting at SDAO');

    expect(fn () => runAdviserStepMigration())->toThrow(RuntimeException::class, 'More than one active');

    expect($waiting->fresh()->workflow_template_id)->toBe($this->legacy->id)
        ->and(WorkflowTemplate::whereNotNull('retired_at')->count())->toBe(0);

    $duplicate->delete();
    WorkflowStep::where('workflow_template_id', $this->legacy->id)->update(['required_approvals' => 1]);

    expect(fn () => runAdviserStepMigration())->toThrow(RuntimeException::class, 'not the expected single SDAO step');
    expect(WorkflowTemplate::whereNotNull('retired_at')->count())->toBe(0);
});

test('on a database with no report template yet the migration does nothing', function () {
    DB::table('workflow_steps')->delete();
    DB::table('workflow_templates')->delete();

    runAdviserStepMigration();

    expect(WorkflowTemplate::count())->toBe(0);
});
