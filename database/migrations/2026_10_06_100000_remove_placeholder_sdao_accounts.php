<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * IdentitySeeder used to seed two placeholder SDAO accounts, "SDAO Member A"
 * (sdao-a) and "SDAO Member B" (sdao-b), next to the real SDAO members
 * (Carl Justin Magpantay, Zaira Joy Enayo) from RealRosterSeeder. The
 * placeholders were only ever demo noise: the approval engine notified them
 * because they held the SDAO seat, but the real review work went to the real
 * members. IdentitySeeder no longer creates them; this is the one-time cleanup
 * for databases that already have them.
 *
 * It is split into plan() and up():
 *  - plan() only READS. It lists the accounts it would delete, everything
 *    attached to them, the notification clean-up, and the result of every
 *    safety check. dryRun() renders it as text.
 *  - up() runs plan(), refuses with nothing changed if any check blocks, and
 *    only then deletes.
 *
 * Safe by construction:
 *  - It matches an account by BOTH email and name, so a real person who
 *    happens to use one of those emails is never touched.
 *  - It refuses if real review work is attached to a placeholder: any
 *    transition, step approval, submitted document, upload, membership, join
 *    or officer-change request, adviser registration, passkey or push token,
 *    or any seat other than SDAO member.
 *  - It refuses unless at least two other SDAO members remain, because the
 *    SDAO step needs both (invariant #3).
 *  - Notifications are only deleted when they are duplicates: every document
 *    a placeholder was notified about must also have notified a remaining
 *    SDAO member. Anything else blocks the migration.
 *  - It deletes with the query builder, so model-level guards stay exactly as
 *    they are for every normal code path. The foreign keys cascade seats and
 *    approver notifications; the polymorphic bell notifications are deleted
 *    explicitly.
 */
return new class extends Migration
{
    /** @var array<string, string> email => the exact name IdentitySeeder gave it */
    private const array PLACEHOLDER_SDAO = [
        'sdao-a@nu-lipa.edu.ph' => 'SDAO Member A',
        'sdao-b@nu-lipa.edu.ph' => 'SDAO Member B',
    ];

    public function up(): void
    {
        $plan = $this->plan();

        if ($plan['blocked'] !== []) {
            throw new RuntimeException(
                'Refusing to remove the placeholder SDAO accounts, nothing was changed. Real work is still attached: '
                .implode('; ', $plan['blocked']).'.'
            );
        }

        $accountIds = collect($plan['accounts'])->pluck('id');

        DB::transaction(function () use ($accountIds) {
            // Bell notifications are polymorphic, so no foreign key cascades them.
            DB::table('notifications')->where('notifiable_type', 'like', '%User')->whereIn('notifiable_id', $accountIds)->delete();

            // Cascades their seats and approver notifications.
            DB::table('users')->whereIn('id', $accountIds)->delete();
        });
    }

    public function down(): void
    {
        // The removed rows are not recreated: they were placeholders.
    }

    /**
     * What up() would delete and keep, and every safety check. Reads only.
     *
     * @return array{accounts: array<int, array<string, mixed>>, skipped: array<int, array<string, mixed>>, remaining_sdao: array<int, array<string, mixed>>, attached: array<string, int>, checks: array<string, int>, blocked: array<int, string>}
     */
    public function plan(): array
    {
        $candidates = DB::table('users')->whereIn('email', array_keys(self::PLACEHOLDER_SDAO))->orderBy('id')->get(['id', 'email', 'name']);

        $accounts = collect();
        $skipped = collect();

        foreach ($candidates as $user) {
            $row = ['id' => $user->id, 'email' => $user->email, 'name' => $user->name];

            if ($user->name === self::PLACEHOLDER_SDAO[$user->email]) {
                $accounts->push($row);
            } else {
                $skipped->push($row + ['reason' => 'name does not match the placeholder, treated as a real account']);
            }
        }

        $accountIds = $accounts->pluck('id');

        $remaining = DB::table('role_assignments')
            ->join('users', 'users.id', '=', 'role_assignments.user_id')
            ->where('role_assignments.role', 'sdao_member')
            ->whereNotIn('users.id', $accountIds)
            ->orderBy('users.id')
            ->get(['users.id', 'users.email', 'users.name']);

        $checks = $this->checks($accountIds, $remaining->pluck('id'));

        $blocked = collect($checks)->filter(fn (int $count) => $count > 0)
            ->map(fn (int $count, string $what) => "{$count} {$what}")
            ->values()
            ->all();

        return [
            'accounts' => $accounts->values()->all(),
            'skipped' => $skipped->values()->all(),
            'remaining_sdao' => $remaining->map(fn ($u) => (array) $u)->all(),
            'attached' => $this->attached($accountIds),
            'checks' => $checks,
            'blocked' => $blocked,
        ];
    }

    /**
     * The plan as readable lines, for a dry run.
     *
     * @return array<int, string>
     */
    public function dryRun(): array
    {
        $plan = $this->plan();
        $lines = ['DRY RUN: nothing has been changed.', ''];

        $section = function (string $title, array $rows, callable $line) use (&$lines) {
            $lines[] = "{$title} (".count($rows).')';

            foreach ($rows as $row) {
                $lines[] = '  - '.$line($row);
            }

            if ($rows === []) {
                $lines[] = '  (none)';
            }

            $lines[] = '';
        };

        $section('ACCOUNTS TO DELETE (not reversible)', $plan['accounts'], fn ($a) => "#{$a['id']} {$a['email']} \"{$a['name']}\"");
        $section('Accounts matched by email but KEPT', $plan['skipped'], fn ($a) => "#{$a['id']} {$a['email']} \"{$a['name']}\": {$a['reason']}");
        $section('SDAO members that remain', $plan['remaining_sdao'], fn ($u) => "#{$u['id']} {$u['email']} \"{$u['name']}\"");

        $lines[] = 'Rows attached to the accounts (removed with them)';

        foreach ($plan['attached'] as $what => $count) {
            $lines[] = "  {$count} {$what}";
        }

        $lines[] = '';
        $lines[] = 'Safety checks (every count must be 0)';

        foreach ($plan['checks'] as $what => $count) {
            $lines[] = '  '.($count === 0 ? '[ok]    ' : '[BLOCK] ')."{$count} {$what}";
        }

        $lines[] = '';
        $lines[] = $plan['blocked'] === []
            ? 'RESULT: all checks pass. The migration would run.'
            : 'RESULT: BLOCKED. The migration would refuse and change nothing.';

        return $lines;
    }

    /**
     * What goes with the accounts, for the report.
     *
     * @param  Collection<int, int>  $accountIds
     * @return array<string, int>
     */
    private function attached(Collection $accountIds): array
    {
        return [
            'seats (sdao_member)' => DB::table('role_assignments')->whereIn('user_id', $accountIds)->count(),
            'approver notifications' => DB::table('approval_notifications')->whereIn('user_id', $accountIds)->count(),
            'bell notifications' => DB::table('notifications')->where('notifiable_type', 'like', '%User')->whereIn('notifiable_id', $accountIds)->count(),
        ];
    }

    /**
     * @param  Collection<int, int>  $accountIds
     * @param  Collection<int, int>  $remainingSdaoIds
     * @return array<string, int>
     */
    private function checks(Collection $accountIds, Collection $remainingSdaoIds): array
    {
        $notifiedDocuments = fn (Collection $userIds): Collection => DB::table('approval_notifications')->whereIn('user_id', $userIds)->pluck('document_id')->unique();

        // A placeholder's approver notification is a duplicate only when a remaining SDAO member was notified about the same document.
        $placeholderDocuments = $notifiedDocuments($accountIds);
        $uncoveredApproverNotifications = $placeholderDocuments->diff($notifiedDocuments($remainingSdaoIds))->count();

        $bellDocuments = fn (Collection $userIds): Collection => DB::table('notifications')
            ->where('notifiable_type', 'like', '%User')
            ->whereIn('notifiable_id', $userIds)
            ->pluck('data')
            ->map(fn ($data) => (json_decode((string) $data, true) ?? [])['document_id'] ?? null)
            ->unique();

        $uncoveredBellNotifications = $bellDocuments($accountIds)->diff($bellDocuments($remainingSdaoIds))->count();

        return [
            // Only meaningful when there is something to remove: a fresh database has no placeholders and must migrate cleanly.
            'fewer than two other SDAO members remaining (the SDAO step needs both)' => $accountIds->isEmpty() || $remainingSdaoIds->count() >= 2 ? 0 : 1,
            'seats other than SDAO member on the removed accounts' => DB::table('role_assignments')->whereIn('user_id', $accountIds)->where('role', '!=', 'sdao_member')->count(),
            'transitions by the removed accounts' => DB::table('document_transitions')->whereIn('actor_id', $accountIds)->count(),
            'step approvals by the removed accounts' => DB::table('document_step_approvals')->whereIn('user_id', $accountIds)->count(),
            'documents submitted by the removed accounts' => DB::table('documents')->whereIn('submitted_by', $accountIds)->count(),
            'uploads by the removed accounts' => DB::table('document_attachments')->whereIn('uploaded_by', $accountIds)->count(),
            'memberships of the removed accounts' => DB::table('organization_memberships')->whereIn('user_id', $accountIds)->count(),
            'join requests by or decided by the removed accounts' => DB::table('organization_join_requests')->where(fn ($q) => $q->whereIn('user_id', $accountIds)->orWhereIn('decided_by', $accountIds))->count(),
            'officer change requests naming the removed accounts' => DB::table('officer_change_requests')
                ->where(fn ($q) => $q->whereIn('requested_by', $accountIds)->orWhereIn('nominee_id', $accountIds)->orWhereIn('outgoing_user_id', $accountIds)->orWhereIn('decided_by', $accountIds))
                ->count(),
            'registrations naming a removed account as adviser' => DB::table('organization_registration_details')->whereIn('adviser_id', $accountIds)->count(),
            'accounts deactivated by a removed account' => DB::table('users')->whereIn('deactivated_by', $accountIds)->count(),
            'passkeys of the removed accounts' => DB::table('passkeys')->whereIn('user_id', $accountIds)->count(),
            'push tokens of the removed accounts' => DB::table('push_tokens')->whereIn('user_id', $accountIds)->count(),
            'documents with approver notifications only the removed accounts received' => $uncoveredApproverNotifications,
            'documents with bell notifications only the removed accounts received' => $uncoveredBellNotifications,
        ];
    }
};
