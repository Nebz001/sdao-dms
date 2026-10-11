<?php

namespace App\Console\Commands;

use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Identity\Admin\AccountDeactivator;
use App\Models\Document;
use App\Models\DocumentStepApproval;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ONE-TIME production cleanup: two accounts that were wrongly given the SDAO
 * member role ("SDAO Member B", the leftover IdentitySeeder placeholder, and
 * "System Admin", an account that only needed admin access) are taken out of
 * the SDAO seat and deactivated.
 *
 * Nothing is deleted: the user rows stay, and document_transitions and
 * document_step_approvals are never written (the log is append-only, and an
 * approval these accounts already gave keeps counting toward its step).
 *
 * Each target must match on assignment id, user id, email AND the sdao_member
 * role. If any row does not match exactly, or the change would leave the SDAO
 * step short of members, the command stops and changes nothing.
 *
 * Deactivation goes through AccountDeactivator, the one place an account is
 * retired (the admin Deactivate action and the SDAO replacement flow use it
 * too). DeactivateAccount itself is not used: once the seat is removed the
 * account holds no role, so it would be handled as a student account.
 *
 * Dry run is the default. Nothing is written without --force.
 */
class RemoveExtraSdaoMembers extends Command
{
    /** @var array<int, array{assignment_id: int, user_id: int, email: string}> */
    private const array TARGETS = [
        ['assignment_id' => 42, 'user_id' => 42, 'email' => 'sdao-b@nu-lipa.edu.ph'],
        ['assignment_id' => 83, 'user_id' => 161, 'email' => 'ranuvirai@gmail.com'],
    ];

    private const string REASON = 'Removed from the SDAO seat: this account is not an SDAO approver. Its history is kept for the audit trail.';

    protected $signature = 'sdao:remove-extra-members
        {--dry-run : Print what would change without writing anything (the default)}
        {--force : Actually remove the two assignments and deactivate the accounts}';

    protected $description = 'One-time: remove the SDAO member role from SDAO Member B and System Admin and deactivate both accounts. Dry run unless --force.';

    public function handle(AccountDeactivator $deactivator): int
    {
        $write = (bool) $this->option('force') && ! $this->option('dry-run');

        $plan = $this->plan();

        $this->line($write ? 'APPLYING CHANGES' : 'DRY RUN: nothing has been changed.');
        $this->line('  environment: '.app()->environment());
        $this->newLine();

        $this->report($plan);

        if ($plan['errors'] !== []) {
            $this->newLine();
            $this->error('STOPPED, nothing was changed:');

            foreach ($plan['errors'] as $error) {
                $this->line("  - {$error}");
            }

            return self::FAILURE;
        }

        if (! $write) {
            $this->newLine();
            $this->info('Dry run only. Re-run with --force to apply.');

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($deactivator) {
                // Re-check under row locks: the dry run may be minutes old.
                RoleAssignment::query()->whereIn('id', array_column(self::TARGETS, 'assignment_id'))->lockForUpdate()->get();
                User::query()->whereIn('id', array_column(self::TARGETS, 'user_id'))->lockForUpdate()->get();

                $recheck = $this->plan();

                if ($recheck['errors'] !== []) {
                    throw new \RuntimeException(implode(' ', $recheck['errors']));
                }

                foreach ($recheck['targets'] as $target) {
                    RoleAssignment::query()->whereKey($target['assignment_id'])->delete();

                    $user = User::query()->findOrFail($target['user_id']);

                    if (! $user->isDeactivated()) {
                        $deactivator->deactivate($user, null, self::REASON);
                    }
                }
            });
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('Rolled back, nothing was changed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Done. Removed '.count(self::TARGETS).' SDAO assignments and deactivated those accounts.');

        return self::SUCCESS;
    }

    /**
     * What would change, and whether it is safe. Reads only.
     *
     * @return array{errors: array<int, string>, targets: array<int, array<string, mixed>>, remaining: Collection<int, User>, required: int, waiting: array<int, array<string, mixed>>}
     */
    private function plan(): array
    {
        $errors = [];
        $targets = [];

        foreach (self::TARGETS as $expected) {
            $label = "assignment #{$expected['assignment_id']} / user #{$expected['user_id']} ({$expected['email']})";
            $assignment = RoleAssignment::query()->find($expected['assignment_id']);
            $user = User::query()->find($expected['user_id']);

            if ($assignment === null || $user === null) {
                $errors[] = "{$label}: not found.";

                continue;
            }

            if ($assignment->user_id !== $user->id || $assignment->role !== Role::SdaoMember) {
                $errors[] = "{$label}: the assignment is not this user's sdao_member assignment.";

                continue;
            }

            if ($user->email !== $expected['email']) {
                $errors[] = "{$label}: the email on record is {$user->email}.";

                continue;
            }

            $otherRoles = RoleAssignment::query()->where('user_id', $user->id)->whereKeyNot($assignment->id)->get();

            if ($otherRoles->isNotEmpty()) {
                $errors[] = "{$label}: also holds other roles (".$otherRoles->map(fn (RoleAssignment $ra) => $ra->role->value)->implode(', ').'), so it is not a plain SDAO-only account.';

                continue;
            }

            $targets[] = [
                'assignment_id' => $assignment->id,
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'already_deactivated' => $user->isDeactivated(),
            ];
        }

        $removedUserIds = array_column(self::TARGETS, 'user_id');

        $remaining = User::query()
            ->active()
            ->whereNotIn('id', $removedUserIds)
            ->whereHas('roleAssignments', fn ($q) => $q->where('role', Role::SdaoMember))
            ->orderBy('id')
            ->get();

        $required = (int) (WorkflowStep::query()->where('role', Role::SdaoMember)->max('required_approvals') ?: 1);

        if ($remaining->count() < $required) {
            $errors[] = "Only {$remaining->count()} active SDAO member(s) would remain, but the SDAO step needs {$required}.";
        }

        return [
            'errors' => $errors,
            'targets' => $targets,
            'remaining' => $remaining,
            'required' => $required,
            'waiting' => $this->waitingAtSdao($removedUserIds, $remaining),
        ];
    }

    /**
     * Documents in review at an SDAO step, and how that step looks afterwards.
     * Approvals are counted by row (any distinct users), so an approval already
     * given by a removed account still counts.
     *
     * @param  array<int, int>  $removedUserIds
     * @param  Collection<int, User>  $remaining
     * @return array<int, array<string, mixed>>
     */
    private function waitingAtSdao(array $removedUserIds, Collection $remaining): array
    {
        $rows = [];

        $documents = Document::query()
            ->where('status', DocumentStatus::InReview)
            ->whereNotNull('current_step_position')
            ->with('workflowTemplate.steps')
            ->orderBy('id')
            ->get();

        foreach ($documents as $document) {
            $step = $document->workflowTemplate?->steps->firstWhere('position', $document->current_step_position);

            if ($step === null || $step->role !== Role::SdaoMember) {
                continue;
            }

            $approvals = DocumentStepApproval::query()
                ->where('document_id', $document->id)
                ->where('workflow_step_id', $step->id)
                ->pluck('user_id');

            $fromRemoved = $approvals->intersect($removedUserIds)->count();
            $stillNeeded = max(0, $step->required_approvals - $approvals->count());
            $canStillApprove = $remaining->reject(fn (User $u) => $approvals->contains($u->id));

            $rows[] = [
                'id' => $document->id,
                'title' => $document->title,
                'given' => $approvals->count(),
                'required' => $step->required_approvals,
                'from_removed' => $fromRemoved,
                'still_needed' => $stillNeeded,
                'can_approve' => $canStillApprove->map(fn (User $u) => "#{$u->id} {$u->name}")->values()->all(),
                'reachable' => $canStillApprove->count() >= $stillNeeded,
            ];
        }

        return $rows;
    }

    /**
     * @param  array{errors: array<int, string>, targets: array<int, array<string, mixed>>, remaining: Collection<int, User>, required: int, waiting: array<int, array<string, mixed>>}  $plan
     */
    private function report(array $plan): void
    {
        $this->line('Would remove the SDAO role from and deactivate ('.count($plan['targets']).' of '.count(self::TARGETS).' matched):');

        foreach ($plan['targets'] as $t) {
            $note = $t['already_deactivated'] ? ' (already deactivated, left as is)' : '';
            $this->line("  - user #{$t['user_id']} \"{$t['name']}\" <{$t['email']}>, assignment #{$t['assignment_id']}{$note}");
        }

        $this->newLine();
        $this->line("Active SDAO members that remain (SDAO step needs {$plan['required']} approvals):");

        foreach ($plan['remaining'] as $u) {
            $this->line("  - user #{$u->id} \"{$u->name}\" <{$u->email}>");
        }

        $this->newLine();
        $this->line('Documents in review at an SDAO step ('.count($plan['waiting']).'):');

        if ($plan['waiting'] === []) {
            $this->line('  (none)');
        }

        foreach ($plan['waiting'] as $w) {
            $this->line("  - document #{$w['id']} \"{$w['title']}\": {$w['given']}/{$w['required']} approvals ({$w['from_removed']} from a removed account, still counted); needs {$w['still_needed']} more");
            $this->line('      can still approve: '.($w['can_approve'] === [] ? '(nobody)' : implode(', ', $w['can_approve'])).($w['reachable'] ? '' : '  ** QUORUM NOT REACHABLE **'));
        }
    }
}
