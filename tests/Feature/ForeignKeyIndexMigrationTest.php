<?php

use Illuminate\Support\Facades\Schema;

function fkIndexMigration(): object
{
    return require database_path('migrations/2026_11_05_100000_index_unindexed_foreign_key_columns.php');
}

function leadingColumns(string $table): array
{
    return collect(Schema::getIndexes($table))->map(fn ($i) => $i['columns'][0] ?? null)->all();
}

it('indexes every flagged foreign key column', function () {
    expect(leadingColumns('documents'))->toContain('submitted_by')
        ->and(leadingColumns('organizations'))->toContain('program_id', 'school_id')
        ->and(leadingColumns('document_step_approvals'))->toContain('user_id', 'workflow_step_id')
        ->and(leadingColumns('users'))->toContain('deactivated_by');
});

it('is safe to run again and rolls back only its own indexes', function () {
    $migration = fkIndexMigration();

    $migration->up();
    expect(collect(Schema::getIndexes('documents'))->where('name', 'documents_submitted_by_index'))->toHaveCount(1);

    $migration->down();
    expect(leadingColumns('documents'))->not->toContain('submitted_by')
        ->and(Schema::getIndexes('documents'))->not->toBeEmpty();

    $migration->up();
    expect(leadingColumns('documents'))->toContain('submitted_by');
});
