<?php

namespace App\Console\Commands;

use App\Models\EmailVerificationCode;
use App\Models\Organization;
use App\Models\WorkflowStep;
use App\Models\WorkflowTemplate;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Resets the dev database to a realistic demo state: wipes organizations,
 * documents (and everything that cascades from them), stale email
 * verification codes, and notifications, then reseeds a believable spread of
 * accounts/organizations/documents via DemoDataSeeder.
 *
 * Deliberately leaves `users`, `passkeys`, `sessions`, `schools`, `programs`,
 * `role_assignments`, and `settings` completely untouched — every existing
 * user account and its role/membership scaffolding survives a reset
 * unmodified. The one exception is duplicate `workflow_templates` (and their
 * `workflow_steps`) — exact re-seeded copies of the real ones with no other
 * table depending on them — which are cleaned up so
 * WorkflowTemplateResolver always resolves deterministically.
 */
class ResetDemoData extends Command
{
    protected $signature = 'demo:reset {--force : Skip the confirmation prompt}';

    protected $description = 'Wipe demo/transactional data (preserving all user accounts and structural config) and reseed a realistic demo dataset.';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run demo:reset in production.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(
            'This deletes all organizations, documents, and related demo data, then reseeds fresh demo data. User accounts are NOT touched. Continue?'
        )) {
            $this->warn('Aborted — nothing was changed.');

            return self::SUCCESS;
        }

        // The pooled Supabase connection inherits whatever statement_timeout
        // the server/pooler role has configured. This command runs as a
        // one-shot artisan process with its own PDO connection, so raising
        // it here is scoped to this process only — it cannot leak into
        // web/queue workers, which each get their own connection. No reset
        // is needed afterward since the process exits when handle() returns.
        DB::statement('SET statement_timeout = 300000'); // 5 minutes, up from the server default

        $this->info('Wiping demo/transactional data...');
        $wipeSummary = DB::transaction(fn () => $this->wipe());

        foreach ($wipeSummary as $label => $count) {
            $this->line("  - {$label}: {$count}");
        }

        $this->newLine();
        $this->info('Seeding realistic demo data (this drives real approval chains, so it takes a moment)...');

        $seedSummary = DB::transaction(function () {
            /** @var DemoDataSeeder $seeder */
            $seeder = app(DemoDataSeeder::class);

            return $seeder->run();
        });

        $this->newLine();
        $this->info('Done. Seeded:');
        foreach ($seedSummary as $label => $count) {
            $this->line("  - {$label}: {$count}");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function wipe(): array
    {
        // Duplicate workflow_templates: same (form_type, variant) pair
        // re-inserted by IdentitySeeder alongside the real ones. Keep the
        // lowest id per pair (the real, original row) and delete the rest —
        // nothing but their own workflow_steps depends on a template, so
        // this is safe regardless of how many duplicates exist.
        $keepIds = WorkflowTemplate::query()
            ->selectRaw('MIN(id) as id')
            ->groupBy('form_type', 'variant')
            ->pluck('id');

        $duplicateTemplateIds = WorkflowTemplate::query()
            ->whereNotIn('id', $keepIds)
            ->pluck('id');

        $duplicateTemplateCount = $duplicateTemplateIds->count();
        WorkflowStep::query()->whereIn('workflow_template_id', $duplicateTemplateIds)->delete();
        WorkflowTemplate::query()->whereIn('id', $duplicateTemplateIds)->delete();

        // Organizations cascade (DB-level FKs) into organization_memberships,
        // documents, and every document child table (step approvals,
        // transitions, approval_notifications, attachments, registration
        // details, activity_calendars/calendar_activities, activity_proposals,
        // after_activity_reports). role_assignments.organization_id is
        // nullOnDelete, so advisers/students bound to a deleted org are freed,
        // not deleted.
        $organizationCount = Organization::query()->count();
        Organization::query()->delete();

        $emailCodeCount = EmailVerificationCode::query()->count();
        EmailVerificationCode::query()->delete();

        $notificationCount = DB::table('notifications')->count();
        DB::table('notifications')->delete();

        return [
            'duplicate workflow_templates removed' => $duplicateTemplateCount,
            'organizations wiped (cascaded to all documents)' => $organizationCount,
            'stale email_verification_codes wiped' => $emailCodeCount,
            'notifications wiped' => $notificationCount,
        ];
    }
}
