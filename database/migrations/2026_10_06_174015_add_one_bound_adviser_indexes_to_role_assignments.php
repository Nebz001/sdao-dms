<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Database backstop for the two adviser rules that until now lived only in
     * code: an organization has at most ONE bound adviser, and an adviser is
     * bound to at most ONE organization. A bound adviser is a role_assignments
     * row with role = 'adviser' and a non-null organization_id; unbound pool
     * advisers are not constrained (many may sit in the pool). Also keeps
     * adviser_terms honest: at most one OPEN term per organization and per user.
     *
     * This migration NEVER guesses. If the table already breaks either rule it
     * fails and names the rows, so a person decides which one is real — nothing
     * is unbound, deleted or "resolved" by age. Nothing is changed on failure.
     *
     * Both Postgres and SQLite accept these partial-index predicates verbatim,
     * so the sqlite :memory: test database runs the same statements.
     */
    public function up(): void
    {
        $problems = [
            ...$this->duplicates('organization_id', 'organization'),
            ...$this->duplicates('user_id', 'adviser account'),
        ];

        if ($problems !== []) {
            throw new RuntimeException(
                'Cannot enforce one bound adviser per organization: '.count($problems).' conflict(s) found. '
                .implode(' ', $problems)
                .' Unbind (set organization_id = null on) the extra row(s) so each organization has one adviser and each adviser one organization, then re-run this migration. Nothing was changed.'
            );
        }

        DB::statement("create unique index role_assignments_one_bound_adviser_per_organization on role_assignments (organization_id) where role = 'adviser' and organization_id is not null");
        DB::statement("create unique index role_assignments_one_bound_organization_per_adviser on role_assignments (user_id) where role = 'adviser' and organization_id is not null");
        DB::statement('create unique index adviser_terms_one_open_per_organization on adviser_terms (organization_id) where ended_at is null');
        DB::statement('create unique index adviser_terms_one_open_per_user on adviser_terms (user_id) where ended_at is null');
    }

    public function down(): void
    {
        DB::statement('drop index if exists role_assignments_one_bound_adviser_per_organization');
        DB::statement('drop index if exists role_assignments_one_bound_organization_per_adviser');
        DB::statement('drop index if exists adviser_terms_one_open_per_organization');
        DB::statement('drop index if exists adviser_terms_one_open_per_user');
    }

    /**
     * One sentence per column value that appears on more than one bound
     * adviser row, naming every role_assignments row involved.
     *
     * @return list<string>
     */
    private function duplicates(string $column, string $label): array
    {
        $bound = fn () => DB::table('role_assignments')
            ->where('role', 'adviser')
            ->whereNotNull('organization_id');

        return $bound()
            ->select($column)
            ->groupBy($column)
            ->havingRaw('count(*) > 1')
            ->orderBy($column)
            ->pluck($column)
            ->map(function ($value) use ($bound, $column, $label) {
                $rows = $bound()->where($column, $value)->orderBy('id')->get(['id', 'user_id', 'organization_id']);
                $name = $column === 'organization_id'
                    ? DB::table('organizations')->where('id', $value)->value('name')
                    : DB::table('users')->where('id', $value)->value('email');
                $detail = $rows->map(fn ($r) => "role_assignments #{$r->id} (user {$r->user_id}, organization {$r->organization_id})")->implode(', ');

                return "The {$label} {$value}".($name !== null ? " ({$name})" : '')." is on {$rows->count()} bound adviser rows: {$detail}.";
            })
            ->all();
    }
};
