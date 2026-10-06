<?php

use App\Identity\Admin\AccountDeactivator;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * IdentitySeeder used to seed two placeholder SDAO accounts, "SDAO Member A"
 * (sdao-a) and "SDAO Member B" (sdao-b), next to the real SDAO members
 * (Carl Justin Magpantay, Zaira Joy Enayo) from RealRosterSeeder. The
 * placeholders were only ever demo noise. IdentitySeeder no longer creates
 * them; this is the one-time cleanup for databases that already have them.
 *
 * This migration must NEVER fail a deploy: migrations run on container start,
 * so a throw here blocks every release. Instead each placeholder gets one of
 * three outcomes, and every one is written to the log:
 *
 *  - delete:     nothing real is attached. The row, its seat and its duplicate
 *                notifications are removed.
 *  - deactivate: real work is attached (transitions, step approvals, ...). The
 *                row is KEPT so the append-only audit history still points at a
 *                real user, and the account is retired through
 *                AccountDeactivator (deactivated_at, a reason, sessions ended).
 *                No document_transitions or step approval row is ever edited,
 *                and the seat is left alone: RoleDirectory::sdaoMembers() only
 *                resolves ACTIVE users, so a deactivated holder stops being an
 *                approver on its own.
 *  - skip:       the account is already deactivated (left exactly as it is), or
 *                the safety check below failed.
 *
 * Safety check: at least one real SDAO member must be active and able to sign
 * in (seat, not deactivated, verified email, verified account). If none is,
 * every placeholder is skipped, nothing changes, and a warning is logged.
 *
 * An account is only treated as a placeholder when BOTH its email and its name
 * match what IdentitySeeder gave it, so a real person who happens to use one of
 * those emails is never touched.
 *
 * It is split into plan() and up(): plan() only READS, dryRun() renders it as
 * text, and up() applies it.
 */
return new class extends Migration
{
    private const string LOG_PREFIX = '[placeholder-sdao] ';

    private const string DEACTIVATION_REASON = 'Placeholder SDAO account retired. It was demo data; its history is kept for the audit trail.';

    /** @var array<string, string> email => the exact name IdentitySeeder gave it */
    private const array PLACEHOLDER_SDAO = [
        'sdao-a@nu-lipa.edu.ph' => 'SDAO Member A',
        'sdao-b@nu-lipa.edu.ph' => 'SDAO Member B',
    ];

    public function up(): void
    {
        $plan = $this->plan();

        if ($plan['accounts'] === [] && $plan['kept'] === []) {
            $this->log('info', 'No placeholder SDAO accounts found. Nothing to do.');

            return;
        }

        foreach ($plan['kept'] as $row) {
            $this->log('info', "Kept #{$row['id']} {$row['email']}: {$row['reason']}.");
        }

        if ($plan['accounts'] === []) {
            return;
        }

        if ($plan['guard'] !== null) {
            $this->log('warning', "Skipped ALL placeholder SDAO accounts, nothing was changed: {$plan['guard']}");

            return;
        }

        $this->log('info', 'Real SDAO accounts that can sign in: '.collect($plan['real_sdao'])->map(fn ($u) => "#{$u['id']} {$u['email']}")->implode(', ').'.');

        foreach ($plan['accounts'] as $account) {
            $label = "#{$account['id']} {$account['email']}";

            match ($account['action']) {
                'delete' => $this->deleteAccount($account['id'], $label),
                'deactivate' => $this->deactivateAccount($account['id'], $label, $account['work']),
                default => $this->log('info', "Skipped {$label}: {$account['why']}."),
            };
        }
    }

    public function down(): void
    {
        // Nothing is recreated or reactivated: the accounts were placeholders.
    }

    /**
     * What up() would do, and why. Reads only.
     *
     * @return array{accounts: array<int, array<string, mixed>>, kept: array<int, array<string, mixed>>, real_sdao: array<int, array<string, mixed>>, guard: string|null}
     */
    public function plan(): array
    {
        $candidates = DB::table('users')->whereIn('email', array_keys(self::PLACEHOLDER_SDAO))->orderBy('id')->get(['id', 'email', 'name', 'deactivated_at']);

        $placeholders = $candidates->filter(fn ($u) => $u->name === self::PLACEHOLDER_SDAO[$u->email])->values();
        $placeholderIds = $placeholders->pluck('id');

        $kept = $candidates->reject(fn ($u) => $placeholderIds->contains($u->id))
            ->map(fn ($u) => ['id' => $u->id, 'email' => $u->email, 'name' => $u->name, 'reason' => 'name does not match the placeholder, treated as a real account'])
            ->values()->all();

        $realSdao = DB::table('role_assignments')
            ->join('users', 'users.id', '=', 'role_assignments.user_id')
            ->where('role_assignments.role', 'sdao_member')
            ->whereNull('users.deactivated_at')
            ->whereNotNull('users.email_verified_at')
            ->where('users.account_status', 'verified')
            ->whereNotIn('users.id', $placeholderIds)
            ->orderBy('users.id')
            ->get(['users.id', 'users.email', 'users.name'])
            ->map(fn ($u) => (array) $u)
            ->all();

        $realSdaoIds = collect($realSdao)->pluck('id');
        $allRealSdaoIds = DB::table('role_assignments')->where('role', 'sdao_member')->whereNotIn('user_id', $placeholderIds)->pluck('user_id');

        $accounts = $placeholders->map(function ($u) use ($realSdaoIds, $allRealSdaoIds) {
            // Duplicate notifications only count as covered by SDAO members who are still there.
            $work = array_filter($this->work($u->id, $allRealSdaoIds->merge($realSdaoIds)->unique()->values()));
            $deactivated = $u->deactivated_at !== null;

            [$action, $why] = match (true) {
                $work === [] => ['delete', 'nothing is attached to it'],
                $deactivated => ['skip', 'it is already deactivated and has history, so it is left exactly as it is'],
                default => ['deactivate', 'it has history that must keep pointing at a real user'],
            };

            return ['id' => $u->id, 'email' => $u->email, 'name' => $u->name, 'action' => $action, 'why' => $why, 'work' => $work];
        })->values()->all();

        $guard = $accounts !== [] && $realSdao === []
            ? 'no real SDAO account is active, has a verified email and a verified account status, so nobody could sign in to cover for the placeholders.'
            : null;

        return ['accounts' => $accounts, 'kept' => $kept, 'real_sdao' => $realSdao, 'guard' => $guard];
    }

    /**
     * The plan as readable lines, for a dry run. Changes nothing.
     *
     * @return array<int, string>
     */
    public function dryRun(): array
    {
        $plan = $this->plan();
        $lines = ['DRY RUN: nothing has been changed.', ''];

        $lines[] = 'Real SDAO accounts that can sign in ('.count($plan['real_sdao']).')';

        foreach ($plan['real_sdao'] as $u) {
            $lines[] = "  - #{$u['id']} {$u['email']} \"{$u['name']}\"";
        }

        $lines[] = '';

        foreach (['delete' => 'WOULD DELETE', 'deactivate' => 'WOULD DEACTIVATE', 'skip' => 'WOULD SKIP'] as $action => $title) {
            $rows = collect($plan['accounts'])->where('action', $action);
            $lines[] = "{$title} (".$rows->count().')';

            foreach ($rows as $a) {
                $lines[] = "  - #{$a['id']} {$a['email']} \"{$a['name']}\": {$a['why']}";

                foreach ($a['work'] as $what => $count) {
                    $lines[] = "      {$count} {$what}";
                }
            }

            if ($rows->isEmpty()) {
                $lines[] = '  (none)';
            }

            $lines[] = '';
        }

        foreach ($plan['kept'] as $k) {
            $lines[] = "KEPT, not a placeholder: #{$k['id']} {$k['email']} \"{$k['name']}\": {$k['reason']}";
        }

        $lines[] = $plan['guard'] !== null
            ? "SAFETY CHECK FAILED: every placeholder would be skipped. {$plan['guard']}"
            : 'Safety check passed.';

        return $lines;
    }

    private function deleteAccount(int $id, string $label): void
    {
        DB::transaction(function () use ($id) {
            // Bell notifications are polymorphic, so no foreign key cascades them.
            DB::table('notifications')->where('notifiable_type', 'like', '%User')->where('notifiable_id', $id)->delete();

            // Cascades its seat and approver notifications.
            DB::table('users')->where('id', $id)->delete();
        });

        $this->log('info', "Deleted {$label}: nothing real was attached.");
    }

    /**
     * @param  array<string, int>  $work
     */
    private function deactivateAccount(int $id, string $label, array $work): void
    {
        $user = User::query()->findOrFail($id);

        app(AccountDeactivator::class)->deactivate($user, null, self::DEACTIVATION_REASON);

        $attached = collect($work)->map(fn (int $count, string $what) => "{$count} {$what}")->implode(', ');
        $this->log('info', "Deactivated {$label}: kept for the audit history ({$attached}). Sessions ended. No history rows were edited.");
    }

    /**
     * Everything real that is attached to ONE account. Empty means it is safe to delete.
     * Its own seat and notifications that a remaining SDAO member also received are
     * not work: they go with the account.
     *
     * @param  Collection<int, int>  $remainingSdaoIds
     * @return array<string, int>
     */
    private function work(int $id, Collection $remainingSdaoIds): array
    {
        $notifiedDocuments = fn (Collection $userIds): Collection => DB::table('approval_notifications')->whereIn('user_id', $userIds)->pluck('document_id')->unique();

        $bellDocuments = fn (Collection $userIds): Collection => DB::table('notifications')
            ->where('notifiable_type', 'like', '%User')
            ->whereIn('notifiable_id', $userIds)
            ->pluck('data')
            ->map(fn ($data) => (json_decode((string) $data, true) ?? [])['document_id'] ?? null)
            ->unique();

        $own = collect([$id]);

        return [
            'seats other than SDAO member' => DB::table('role_assignments')->where('user_id', $id)->where('role', '!=', 'sdao_member')->count(),
            'transitions' => DB::table('document_transitions')->where('actor_id', $id)->count(),
            'step approvals' => DB::table('document_step_approvals')->where('user_id', $id)->count(),
            'documents submitted' => DB::table('documents')->where('submitted_by', $id)->count(),
            'uploads' => DB::table('document_attachments')->where('uploaded_by', $id)->count(),
            'memberships' => DB::table('organization_memberships')->where('user_id', $id)->count(),
            'join requests' => DB::table('organization_join_requests')->where(fn ($q) => $q->where('user_id', $id)->orWhere('decided_by', $id))->count(),
            'officer change requests' => DB::table('officer_change_requests')
                ->where(fn ($q) => $q->where('requested_by', $id)->orWhere('nominee_id', $id)->orWhere('outgoing_user_id', $id)->orWhere('decided_by', $id))
                ->count(),
            'registrations naming it as adviser' => DB::table('organization_registration_details')->where('adviser_id', $id)->count(),
            'accounts it deactivated' => DB::table('users')->where('deactivated_by', $id)->count(),
            'passkeys' => DB::table('passkeys')->where('user_id', $id)->count(),
            'push tokens' => DB::table('push_tokens')->where('user_id', $id)->count(),
            'approver notifications no remaining SDAO member also received' => $notifiedDocuments($own)->diff($notifiedDocuments($remainingSdaoIds))->count(),
            'bell notifications no remaining SDAO member also received' => $bellDocuments($own)->diff($bellDocuments($remainingSdaoIds))->count(),
        ];
    }

    /**
     * Written to the application log and, when run from the console (a deploy),
     * to stderr so the Railway deploy log shows it whatever the log channel is.
     */
    private function log(string $level, string $message): void
    {
        $message = self::LOG_PREFIX.$message;

        Log::{$level}($message);

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            fwrite(STDERR, strtoupper($level).' '.$message.PHP_EOL);
        }
    }
};
