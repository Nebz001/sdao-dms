<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foreign key columns the Supabase Performance Advisor flagged as
     * unindexed, by table. Postgres does not index the referencing side of a
     * foreign key on its own, so joins and cascading deletes on these scan.
     *
     * Each column is indexed only if it exists and no current index (plain,
     * unique, composite or primary) already has it as its leading column;
     * anything already covered is logged and skipped, never an error. Indexes
     * keep Laravel's default name ({table}_{column}_index) so down() can drop
     * exactly the ones created here. Existing indexes are never dropped.
     * Schema::getIndexes() reads both SQLite and Postgres, so no driver guard
     * is needed.
     *
     * @var array<string, list<string>>
     */
    private const COLUMNS = [
        'activity_proposals' => ['calendar_activity_id'],
        'adviser_terms' => ['ended_by', 'started_by'],
        'after_activity_reports' => ['activity_proposal_id'],
        'approval_notifications' => ['user_id'],
        'calendar_activities' => ['activity_calendar_id'],
        'document_attachments' => ['uploaded_by'],
        'document_remarks' => ['user_id'],
        'document_reminders' => ['sent_by'],
        'document_step_approvals' => ['user_id', 'workflow_step_id'],
        'document_transitions' => ['actor_id'],
        'documents' => ['submitted_by'],
        'email_verification_codes' => ['user_id'],
        'officer_change_requests' => ['decided_by', 'outgoing_user_id'],
        'organization_join_requests' => ['decided_by'],
        'organization_registration_details' => ['adviser_id'],
        'organizations' => ['program_id', 'school_id'],
        'programs' => ['school_id'],
        'role_assignments' => ['program_id', 'school_id'],
        'users' => ['deactivated_by'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                Log::info("Skipping indexes on {$table}: table does not exist.");

                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    Log::info("Skipping index on {$table}.{$column}: column does not exist.");

                    continue;
                }

                if ($this->isCovered($table, $column)) {
                    Log::info("Skipping index on {$table}.{$column}: already the leading column of an existing index.");

                    continue;
                }

                Schema::table($table, fn ($blueprint) => $blueprint->index($column));
            }
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                $name = "{$table}_{$column}_index";

                if ($this->hasIndexNamed($table, $name)) {
                    Schema::table($table, fn ($blueprint) => $blueprint->dropIndex($name));
                }
            }
        }
    }

    private function isCovered(string $table, string $column): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['columns'][0] ?? null) === $column) {
                return true;
            }
        }

        return false;
    }

    private function hasIndexNamed(string $table, string $name): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['name'] === $name) {
                return true;
            }
        }

        return false;
    }
};
