<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * IdentitySeeder used to seed placeholder data that is not part of NU Lipa:
 * three schools that do not exist (the real four are in RealRosterSeeder), the
 * programs, people and organizations that only existed to populate them, and a
 * set of invented placeholder directors, principal, students and
 * organizations. IdentitySeeder no longer creates any of it; this is the
 * one-time cleanup for databases that already have it.
 *
 * It is split into plan() and up():
 *  - plan() only READS. It lists every school, program, seat, organization and
 *    account it would delete (by id and name), the accounts it would keep and
 *    why, and the result of every safety check. dryRun() renders it as text.
 *  - up() runs plan(), refuses with nothing changed if any check blocks, and
 *    only then deletes.
 *
 * Safe by construction:
 *  - It refuses if real work is attached: a document, join request or
 *    officer-change request on a removed organization; any history on a
 *    removed account (approvals, transitions, submissions, memberships,
 *    notifications, uploads, registrations naming it as adviser); or a
 *    removed account that is the only holder of a seat a real chain needs.
 *  - Real data is never touched. An account that also holds a real seat stays,
 *    losing only its fake seat. Advisers with an organization are never
 *    deleted (the demo uses them).
 *  - It deletes with the query builder, so School::booted()'s "schools cannot
 *    be deleted" guard stays exactly as it is for every normal code path. The
 *    foreign keys cascade programs, seats, memberships and requests.
 */
return new class extends Migration
{
    private const array INVALID_SCHOOLS = [
        'School of Computing and IT',
        'School of Business and Accountancy',
        'School of Health Sciences',
    ];

    /** Invented organizations seeded by IdentitySeeder or its fixture seeders. */
    private const array PLACEHOLDER_ORGANIZATIONS = [
        'Computing Society',
        'IT Guild',
        'SHS Student Council',
        'University Chess Club',
    ];

    /** Invented accounts that exist only as placeholders (never a real person's account). */
    private const array PLACEHOLDER_ACCOUNTS = [
        'asst-director@nu-lipa.edu.ph',
        'academic-director@nu-lipa.edu.ph',
        'executive-director@nu-lipa.edu.ph',
        'principal-shs@nu-lipa.edu.ph',
        'adviser-extracurricular@nu-lipa.edu.ph',
        'student-alpha@students.nu-lipa.edu.ph',
        'student-beta@students.nu-lipa.edu.ph',
        'student-gamma@students.nu-lipa.edu.ph',
        'student-delta@students.nu-lipa.edu.ph',
        'student-epsilon@students.nu-lipa.edu.ph',
    ];

    /** Single-holder seats: a placeholder may only be removed while someone else holds the same seat. */
    private const array SINGLE_HOLDER_ROLES = [
        'assistant_director_academic_services',
        'academic_director',
        'executive_director',
        'principal',
    ];

    public function up(): void
    {
        $plan = $this->plan();

        if ($plan['blocked'] !== []) {
            throw new RuntimeException(
                'Refusing to remove the placeholder data, nothing was changed. Real work is still attached: '
                .implode('; ', $plan['blocked']).'.'
            );
        }

        $schoolIds = collect($plan['schools'])->pluck('id');
        $organizationIds = collect($plan['organizations'])->pluck('id');
        $accountIds = collect($plan['accounts'])->pluck('id');

        DB::transaction(function () use ($schoolIds, $organizationIds, $accountIds) {
            // Cascades each organization's memberships, join and change requests.
            DB::table('organizations')->whereIn('id', $organizationIds)->delete();

            // Cascades their seats, tokens, passkeys and push tokens.
            DB::table('users')->whereIn('id', $accountIds)->delete();

            // Cascades the placeholder programs and any remaining seats there.
            DB::table('schools')->whereIn('id', $schoolIds)->delete();
        });
    }

    public function down(): void
    {
        // The removed rows are not recreated: they were never valid.
    }

    /**
     * What up() would delete and keep, and every safety check. Reads only.
     *
     * @return array{schools: array<int, array<string, mixed>>, programs: array<int, array<string, mixed>>, seats: array<int, array<string, mixed>>, organizations: array<int, array<string, mixed>>, accounts: array<int, array<string, mixed>>, kept: array<int, array<string, mixed>>, checks: array<string, int>, blocked: array<int, string>}
     */
    public function plan(): array
    {
        $schools = DB::table('schools')->whereIn('name', self::INVALID_SCHOOLS)->get(['id', 'name']);
        $schoolIds = $schools->pluck('id');
        $programs = DB::table('programs')->whereIn('school_id', $schoolIds)->get(['id', 'name', 'school_id']);
        $programIds = $programs->pluck('id');

        $organizations = DB::table('organizations')
            ->where(fn ($q) => $q->whereIn('school_id', $schoolIds)->orWhereIn('program_id', $programIds)->orWhereIn('name', self::PLACEHOLDER_ORGANIZATIONS))
            ->get(['id', 'name', 'school_id', 'program_id']);
        $organizationIds = $organizations->pluck('id');

        $seats = DB::table('role_assignments')
            ->join('users', 'users.id', '=', 'role_assignments.user_id')
            ->where(fn ($q) => $q->whereIn('role_assignments.school_id', $schoolIds)->orWhereIn('role_assignments.program_id', $programIds))
            ->get(['role_assignments.id', 'role_assignments.role', 'role_assignments.user_id', 'users.email', 'role_assignments.school_id', 'role_assignments.program_id']);
        $seatIds = $seats->pluck('id');

        [$accounts, $kept] = $this->accounts($seatIds, $organizationIds);
        $accountIds = $accounts->pluck('id');

        $checks = $this->checks($organizationIds, $accountIds);

        $blocked = collect($checks)->filter(fn (int $count) => $count > 0)
            ->map(fn (int $count, string $what) => "{$count} {$what}")
            ->values()
            ->all();

        return [
            'schools' => $schools->map(fn ($s) => (array) $s)->all(),
            'programs' => $programs->map(fn ($p) => (array) $p)->all(),
            'seats' => $seats->map(fn ($s) => (array) $s)->all(),
            'organizations' => $organizations->map(fn ($o) => (array) $o)->all(),
            'accounts' => $accounts->values()->all(),
            'kept' => $kept->values()->all(),
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

        $section('Schools to delete', $plan['schools'], fn ($s) => "#{$s['id']} {$s['name']}");
        $section('Programs to delete', $plan['programs'], fn ($p) => "#{$p['id']} {$p['name']} (school #{$p['school_id']})");
        $section('Seats removed with them', $plan['seats'], fn ($s) => "#{$s['id']} {$s['role']} held by {$s['email']} (school ".($s['school_id'] ?? '-').', program '.($s['program_id'] ?? '-').')');
        $section('Organizations to delete', $plan['organizations'], fn ($o) => "#{$o['id']} {$o['name']}");
        $section('ACCOUNTS TO DELETE (not reversible)', $plan['accounts'], fn ($a) => "#{$a['id']} {$a['email']} \"{$a['name']}\": {$a['reason']}");
        $section('Accounts matched but KEPT', $plan['kept'], fn ($a) => "#{$a['id']} {$a['email']} \"{$a['name']}\": {$a['reason']}");

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
     * Accounts that exist only because of the removed data, and those matched but kept.
     *
     * @param  Collection<int, int>  $seatIds
     * @param  Collection<int, int>  $organizationIds
     * @return array{0: Collection<int, array<string, mixed>>, 1: Collection<int, array<string, mixed>>}
     */
    private function accounts(Collection $seatIds, Collection $organizationIds): array
    {
        $holderIds = DB::table('role_assignments')->whereIn('id', $seatIds)->where('role', '!=', 'adviser')->pluck('user_id');

        $candidates = DB::table('users')
            ->where(fn ($q) => $q
                ->whereIn('id', $holderIds)
                ->orWhereIn('email', self::PLACEHOLDER_ACCOUNTS)
                ->orWhereIn('id', DB::table('role_assignments')->where('role', 'student')->whereIn('organization_id', $organizationIds)->select('user_id')))
            ->orderBy('id')
            ->get(['id', 'email', 'name']);

        $accounts = collect();
        $kept = collect();

        foreach ($candidates as $user) {
            $blockingSeat = $this->seatThatKeeps($user->id, $seatIds, $organizationIds);

            if ($blockingSeat === null) {
                $accounts->push(['id' => $user->id, 'email' => $user->email, 'name' => $user->name, 'reason' => 'placeholder account, no real seat']);
            } else {
                $kept->push(['id' => $user->id, 'email' => $user->email, 'name' => $user->name, 'reason' => $blockingSeat]);
            }
        }

        return [$accounts, $kept];
    }

    /**
     * Why this account must stay, or null when every seat it holds goes with the removed data.
     *
     * @param  Collection<int, int>  $seatIds
     * @param  Collection<int, int>  $organizationIds
     */
    private function seatThatKeeps(int $userId, Collection $seatIds, Collection $organizationIds): ?string
    {
        foreach (DB::table('role_assignments')->where('user_id', $userId)->get() as $seat) {
            $removedWithData = $seatIds->contains($seat->id)
                || ($seat->organization_id !== null && $organizationIds->contains($seat->organization_id))
                // The unassigned pools: a student or adviser seat bound to no organization.
                || ($seat->organization_id === null && in_array($seat->role, ['student', 'adviser'], true));

            if ($removedWithData) {
                continue;
            }

            // A seat that a real chain needs, with no other holder to take over, blocks removal.
            if (in_array($seat->role, self::SINGLE_HOLDER_ROLES, true)) {
                $otherHolder = DB::table('role_assignments')
                    ->where('role', $seat->role)
                    ->where('id', '!=', $seat->id)
                    ->where('school_id', $seat->school_id)
                    ->exists();

                if ($otherHolder) {
                    continue;
                }

                return "holds the only {$seat->role} seat";
            }

            return "also holds a real {$seat->role} seat";
        }

        // Advisers of a real organization are never deleted, even by email match.
        return null;
    }

    /**
     * @param  Collection<int, int>  $organizationIds
     * @param  Collection<int, int>  $accountIds
     * @return array<string, int>
     */
    private function checks(Collection $organizationIds, Collection $accountIds): array
    {
        return [
            'documents on the removed organizations' => DB::table('documents')->whereIn('organization_id', $organizationIds)->count(),
            'join requests on the removed organizations' => DB::table('organization_join_requests')->whereIn('organization_id', $organizationIds)->count(),
            'officer change requests on the removed organizations' => DB::table('officer_change_requests')->whereIn('organization_id', $organizationIds)->count(),
            'transitions by the removed accounts' => DB::table('document_transitions')->whereIn('actor_id', $accountIds)->count(),
            'step approvals by the removed accounts' => DB::table('document_step_approvals')->whereIn('user_id', $accountIds)->count(),
            'documents submitted by the removed accounts' => DB::table('documents')->whereIn('submitted_by', $accountIds)->count(),
            'memberships of the removed accounts elsewhere' => DB::table('organization_memberships')->whereIn('user_id', $accountIds)->whereNotIn('organization_id', $organizationIds)->count(),
            'join requests by the removed accounts elsewhere' => DB::table('organization_join_requests')->whereIn('user_id', $accountIds)->whereNotIn('organization_id', $organizationIds)->count(),
            'officer change requests naming the removed accounts elsewhere' => DB::table('officer_change_requests')
                ->where(fn ($q) => $q->whereIn('requested_by', $accountIds)->orWhereIn('nominee_id', $accountIds)->orWhereIn('outgoing_user_id', $accountIds)->orWhereIn('decided_by', $accountIds))
                ->whereNotIn('organization_id', $organizationIds)
                ->count(),
            'approver notifications to the removed accounts' => DB::table('approval_notifications')->whereIn('user_id', $accountIds)->count(),
            'bell notifications to the removed accounts' => DB::table('notifications')->where('notifiable_type', 'like', '%User')->whereIn('notifiable_id', $accountIds)->count(),
            'uploads by the removed accounts' => DB::table('document_attachments')->whereIn('uploaded_by', $accountIds)->count(),
            'registrations naming a removed account as adviser' => DB::table('organization_registration_details')->whereIn('adviser_id', $accountIds)->count(),
        ];
    }
};
