<?php

namespace App\Console\Commands;

use App\Models\DocumentAttachment;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * One-time backfill for the Supabase attachment migration (see AttachmentStorage's
 * class docblock): copies every document_attachments row still on the 'local'
 * disk into 'supabase', verifies the copy byte-for-byte (SHA-256 + length, both
 * read back from disk — never trusting the nullable, advisory `size` column),
 * and only then flips that row's `disk` column. Local files are never deleted —
 * this keeps the whole run reversible, and lets a partially-failed run be
 * safely re-run (an already-copied-and-identical object is "adopted": the
 * upload is skipped but the row still gets updated).
 *
 * Deliberately reads whole files into memory rather than streaming: the
 * per-attachment ceiling is DOCUMENT_MAX_KB (see AttachmentSlots, currently
 * 10 MB), and SHA-256 verification needs the bytes in hand regardless — a
 * streaming implementation would only avoid holding them, not reading them.
 * Revisit this if DOCUMENT_MAX_KB is ever raised past ~100 MB.
 *
 * No DB transaction wraps the batch (unlike ResetDemoData): a rollback cannot
 * un-upload an object already sitting in the bucket, so a batch transaction
 * would only erase good rows' DB updates while leaving their uploads behind —
 * the worst possible outcome. Each row's own update is a single atomic
 * UPDATE statement, which is all the atomicity that's needed.
 */
class MigrateAttachmentsToSupabase extends Command
{
    private const string SOURCE_DISK = 'local';

    private const string TARGET_DISK = 'supabase';

    private const string OUTCOME_MIGRATED = 'migrated';

    private const string OUTCOME_ADOPTED = 'adopted (already on supabase, verified identical)';

    private const string OUTCOME_SKIPPED = 'skipped';

    private const string OUTCOME_FAILED = 'failed';

    /** @var array<int, string> attachment id => reason, for the summary */
    private array $failureReasons = [];

    /** @var array<int, string> attachment id => reason, for the summary */
    private array $skipReasons = [];

    protected $signature = 'attachments:migrate-to-supabase
        {--dry-run : List what would be migrated without copying anything or writing to the database}
        {--overwrite : Replace a differing object that already exists at the same key on supabase}
        {--limit= : Only process this many rows (a canary batch before running the full backfill)}
        {--force : Skip the confirmation prompt}';

    protected $description = 'One-time backfill: copy every document attachment still on the local disk into Supabase Storage, verify it byte-for-byte, then flip the row to disk=supabase. Local files are kept as a fallback.';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        $total = $this->pendingQuery()->count();

        if ($total === 0) {
            $this->info('Nothing to migrate — no document_attachments rows on the local disk.');

            return self::SUCCESS;
        }

        $bucket = (string) config('filesystems.disks.supabase.bucket');
        $endpoint = (string) config('filesystems.disks.supabase.endpoint');
        $approxBytes = (int) $this->pendingQuery()->sum('size');
        $unknownSize = $this->pendingQuery()->whereNull('size')->count();

        if (! $isDryRun && ! $this->assertTargetConfigured()) {
            return self::FAILURE;
        }

        $this->printPreflight($total, $approxBytes, $unknownSize, $bucket, $endpoint);

        if ($isDryRun) {
            $this->dryRun();

            return self::SUCCESS;
        }

        if (! app()->environment('production')) {
            $this->warn(
                'You are running outside production (APP_ENV='.app()->environment().'). If this database was '.
                'seeded by `demo:reset`, every local-disk attachment is throwaway demo data — DemoDataSeeder pins '.
                "filesystems.attachments to 'local' for the duration of its run — and it will be uploaded into ".
                "bucket '{$bucket}'."
            );
        }

        if (! $this->option('force') && ! $this->confirm(
            "This copies {$total} attachment(s) into Supabase bucket '{$bucket}' and updates their disk column ".
            'once each copy is verified. Local files are NOT deleted. Continue?'
        )) {
            $this->warn('Aborted — nothing was changed.');

            return self::SUCCESS;
        }

        $counts = [
            self::OUTCOME_MIGRATED => 0,
            self::OUTCOME_ADOPTED => 0,
            self::OUTCOME_SKIPPED => 0,
            self::OUTCOME_FAILED => 0,
        ];

        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $processed = 0;

        $this->newLine();
        $this->info('Migrating...');

        $this->pendingQuery()->chunkById(100, function (Collection $attachments) use (&$counts, &$processed, $limit): bool {
            /** @var DocumentAttachment $attachment */
            foreach ($attachments as $attachment) {
                if ($limit !== null && $processed >= $limit) {
                    return false;
                }

                $counts[$this->migrateOne($attachment)]++;
                $processed++;
            }

            return true;
        });

        $this->newLine();
        $this->printSummary($counts);

        return $this->failureReasons === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return Builder<DocumentAttachment>
     */
    private function pendingQuery(): Builder
    {
        return DocumentAttachment::query()->where('disk', self::SOURCE_DISK);
    }

    private function assertTargetConfigured(): bool
    {
        if (config('filesystems.disks.supabase.bucket') && config('filesystems.disks.supabase.key')) {
            return true;
        }

        $this->error(
            'SUPABASE_STORAGE_BUCKET / SUPABASE_STORAGE_KEY are not configured — refusing to run. '.
            'Use --dry-run to inspect what would be migrated without live credentials.'
        );

        return false;
    }

    private function printPreflight(int $total, int $approxBytes, int $unknownSize, string $bucket, string $endpoint): void
    {
        $this->info('Attachment Supabase backfill');
        $this->line('  - target disk: '.self::TARGET_DISK." (bucket: {$bucket}, endpoint: {$endpoint})");
        $this->line('  - environment: '.app()->environment());
        $this->line("  - rows on local disk: {$total}");
        $this->line('  - approximate total size: '.number_format($approxBytes / 1024, 1).' KB'.
            ($unknownSize > 0 ? " ({$unknownSize} row(s) have no recorded size)" : ''));
        $this->newLine();
    }

    private function dryRun(): void
    {
        $local = Storage::disk(self::SOURCE_DISK);

        $this->pendingQuery()->chunkById(100, function (Collection $attachments) use ($local): void {
            /** @var DocumentAttachment $attachment */
            foreach ($attachments as $attachment) {
                if (! $local->exists($attachment->path)) {
                    $this->line("  - #{$attachment->id} {$attachment->path}: would be SKIPPED — local file missing");

                    continue;
                }

                $size = $local->size($attachment->path);
                $this->line("  - #{$attachment->id} {$attachment->path}: would migrate ({$size} bytes)");
            }
        });

        $this->newLine();
        $this->info('Dry run complete — nothing was copied or updated.');
    }

    /**
     * Copies one attachment's bytes to Supabase, verifies the copy is
     * byte-identical, then updates the row's disk column. Returns one of the
     * OUTCOME_* constants; the DB row is only ever mutated on MIGRATED or
     * ADOPTED, never on SKIPPED or FAILED.
     */
    private function migrateOne(DocumentAttachment $attachment): string
    {
        $path = $attachment->path;
        $local = Storage::disk(self::SOURCE_DISK);
        $remote = Storage::disk(self::TARGET_DISK);

        try {
            // Checked first: FilesystemAdapter::size() has no try/catch and
            // throws on a missing file even on a disk configured with
            // 'throw' => false, so this guard also protects against that.
            if (! $local->exists($path)) {
                $this->skipReasons[$attachment->id] = 'local file missing (orphaned row)';
                $this->line("  - #{$attachment->id} {$path}: SKIP — local file missing (orphaned row)");

                return self::OUTCOME_SKIPPED;
            }

            $contents = $local->get($path);

            // Strict null check: get() returns null only on read failure —
            // '' is a legitimate zero-byte file and must not be treated as one.
            if ($contents === null) {
                $this->failureReasons[$attachment->id] = 'local file exists but could not be read';
                $this->line("  - #{$attachment->id} {$path}: FAILED — local file exists but could not be read");

                return self::OUTCOME_FAILED;
            }

            $bytes = strlen($contents);
            $hash = hash('sha256', $contents);

            if ($remote->exists($path)) {
                $remoteHash = hash('sha256', (string) $remote->get($path));

                if ($remoteHash === $hash) {
                    // A prior run already copied and verified these exact
                    // bytes but died before updating the row — adopt them.
                    $this->markMigrated($attachment);
                    $this->line("  - #{$attachment->id} {$path}: ADOPTED — already on supabase, verified identical ({$bytes} bytes)");

                    return self::OUTCOME_ADOPTED;
                }

                if (! $this->option('overwrite')) {
                    $reason = 'an object already exists at this key on supabase and differs from the local file (re-run with --overwrite to replace it)';
                    $this->failureReasons[$attachment->id] = $reason;
                    $this->line("  - #{$attachment->id} {$path}: FAILED — {$reason}");

                    return self::OUTCOME_FAILED;
                }
            }

            $remote->put($path, $contents);

            $readBack = $remote->get($path);

            if ($readBack === null || strlen($readBack) !== $bytes || hash('sha256', $readBack) !== $hash) {
                $reason = 'verification failed after copy (read-back did not match the local file)';
                $this->failureReasons[$attachment->id] = $reason;
                $this->line("  - #{$attachment->id} {$path}: FAILED — {$reason}");

                return self::OUTCOME_FAILED;
            }

            $this->markMigrated($attachment);
            $this->line("  - #{$attachment->id} {$path}: OK — migrated and verified ({$bytes} bytes)");

            return self::OUTCOME_MIGRATED;
        } catch (Throwable $e) {
            report($e);

            $this->failureReasons[$attachment->id] = $e->getMessage();
            $this->line("  - #{$attachment->id} {$path}: FAILED — {$e->getMessage()}");

            return self::OUTCOME_FAILED;
        }
    }

    private function markMigrated(DocumentAttachment $attachment): void
    {
        DocumentAttachment::withoutTimestamps(
            fn () => $attachment->update(['disk' => self::TARGET_DISK])
        );
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function printSummary(array $counts): void
    {
        $this->info('Done. Summary:');

        foreach ($counts as $label => $count) {
            $this->line("  - {$label}: {$count}");
        }

        if ($this->skipReasons !== []) {
            $this->newLine();
            $this->warn('Skipped rows:');

            foreach ($this->skipReasons as $id => $reason) {
                $this->line("  - #{$id}: {$reason}");
            }
        }

        if ($this->failureReasons !== []) {
            $this->newLine();
            $this->error('Failed rows (disk column left unchanged, safe to re-run):');

            foreach ($this->failureReasons as $id => $reason) {
                $this->line("  - #{$id}: {$reason}");
            }
        }
    }
}
