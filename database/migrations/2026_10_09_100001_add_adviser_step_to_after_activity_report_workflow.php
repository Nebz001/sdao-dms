<?php

use App\Approval\Contracts\ApproverNotifier;
use App\Enums\TransitionAction;
use App\Identity\RoleDirectory;
use App\Models\Document;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    private const FORM_TYPE = 'after_activity_report';

    private const APPROVER_ACTIONS = ['approved', 'advanced', 'returned', 'rejected', 'completed'];

    /**
     * The After-Activity Report chain becomes Adviser -> SDAO (was SDAO only).
     *
     * Documents read their steps live from their own template by step
     * position, and transitions, approvals and wait stats all key off those
     * positions, so the old template is NOT edited in place: renumbering SDAO
     * from 1 to 2 would relabel every finished report's history. Instead the
     * old template is retired (kept, never picked again) and a new one holds
     * the new chain.
     *
     * Documents already in flight:
     *  - Finished (approved/rejected), draft and returned reports stay on the
     *    retired template, untouched.
     *  - A report in review that no approver has acted on yet moves to the new
     *    template at step 1 (the adviser), provided its organization has an
     *    active adviser to receive it.
     *  - A report SDAO has already started on (an approval) finishes on the old
     *    route: moving it would re-read SDAO's logged decision against the
     *    wrong step, and clearing the approval would throw a real decision away.
     *  - A report whose organization has no active adviser stays on the old
     *    route rather than sitting at a step nobody can act on.
     *
     * No document_transitions row is edited or deleted. Idempotent: nothing
     * happens once the active template already starts with the adviser step.
     * It never guesses: a duplicated or unexpectedly shaped template fails
     * loudly (and changes nothing) instead of being repaired.
     */
    public function up(): void
    {
        $movedDocumentIds = [];

        DB::transaction(function () use (&$movedDocumentIds) {
            $active = DB::table('workflow_templates')
                ->where('form_type', self::FORM_TYPE)
                ->whereNull('variant')
                ->whereNull('retired_at')
                ->get();

            // Fresh database: WorkflowTemplateSeeder creates the new chain.
            if ($active->isEmpty()) {
                return;
            }

            if ($active->count() > 1) {
                throw new RuntimeException(
                    'More than one active After-Activity Report template (ids '.$active->pluck('id')->implode(', ').'). Remove the duplicate, then run this migration again.'
                );
            }

            $old = $active->first();
            $steps = DB::table('workflow_steps')->where('workflow_template_id', $old->id)->orderBy('position')->get();

            if ($steps->first()?->role === 'adviser') {
                return;
            }

            $isSdaoOnly = $steps->count() === 1
                && $steps->first()->role === 'sdao_member'
                && (int) $steps->first()->position === 1
                && (int) $steps->first()->required_approvals === 2;

            if (! $isSdaoOnly) {
                throw new RuntimeException(
                    "After-Activity Report template [{$old->id}] is not the expected single SDAO step. Fix it by hand, then run this migration again."
                );
            }

            $now = now();
            $newId = DB::table('workflow_templates')->insertGetId([
                'form_type' => self::FORM_TYPE,
                'variant' => null,
                'name' => $old->name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('workflow_steps')->insert([
                ['workflow_template_id' => $newId, 'position' => 1, 'role' => 'adviser', 'required_approvals' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['workflow_template_id' => $newId, 'position' => 2, 'role' => 'sdao_member', 'required_approvals' => 2, 'created_at' => $now, 'updated_at' => $now],
            ]);

            DB::table('workflow_templates')->where('id', $old->id)->update(['retired_at' => $now, 'updated_at' => $now]);

            $movedDocumentIds = DB::table('documents')
                ->where('workflow_template_id', $old->id)
                ->where('status', 'in_review')
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('document_transitions')
                    ->whereColumn('document_transitions.document_id', 'documents.id')
                    ->whereIn('document_transitions.action', self::APPROVER_ACTIONS))
                ->whereExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('role_assignments')
                    ->join('users', 'users.id', '=', 'role_assignments.user_id')
                    ->whereColumn('role_assignments.organization_id', 'documents.organization_id')
                    ->where('role_assignments.role', 'adviser')
                    ->whereNull('users.deactivated_at'))
                ->pluck('id')
                ->all();

            DB::table('documents')->whereIn('id', $movedDocumentIds)->update([
                'workflow_template_id' => $newId,
                'current_step_position' => 1,
            ]);
        });

        $this->notifyAdvisers($movedDocumentIds);
    }

    /**
     * A moved report now waits at its adviser, so they get the same hand-off
     * as a fresh submission. Best-effort and after commit: a mail problem must
     * never fail a deploy, and the review queue shows the report regardless.
     *
     * @param  array<int, int>  $documentIds
     */
    private function notifyAdvisers(array $documentIds): void
    {
        foreach ($documentIds as $documentId) {
            try {
                $document = Document::query()->with('organization')->findOrFail($documentId);
                $adviser = app(RoleDirectory::class)->adviserFor($document->organization);

                app(ApproverNotifier::class)->notify($adviser, $document, 1, TransitionAction::Submitted);
            } catch (Throwable $e) {
                Log::warning('Could not notify the adviser of a re-routed after-activity report', [
                    'document_id' => $documentId,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Not reversible: reports that already moved carry adviser approvals that
     * have no place on the single-step chain. Restore a backup instead.
     */
    public function down(): void
    {
        throw new RuntimeException('This migration cannot be reversed; restore a database backup instead.');
    }
};
