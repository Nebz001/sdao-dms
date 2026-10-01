<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * One time command. Finds accounts whose password hash still matches the
 * retired shared default and flags them must_change_password, so their next
 * login lands on the change password page (see EnsurePasswordIsChanged).
 *
 * This is the ONE place the retired default still appears in application
 * code, because detecting it needs the literal. Delete this command after
 * the one time run in each environment.
 *
 * Never prints or logs any password. Output is a count and email addresses.
 */
class FlagDefaultPasswordUsers extends Command
{
    private const string RETIRED_DEFAULT = 'ict@1234';

    protected $signature = 'accounts:flag-default-password-users
        {--dry-run : Report the accounts that would be flagged without writing anything}
        {--except=* : An email to skip, repeatable (--except=a@x.com --except=b@x.com)}
        {--force : Skip the confirmation prompt}';

    protected $description = 'One time: flag accounts still using the retired shared default password so they must change it at next login.';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        /** @var array<int, string> $except */
        $except = collect((array) $this->option('except'))
            ->map(fn ($email) => Str::lower(trim((string) $email)))
            ->filter()
            ->values()
            ->all();

        // Lets the dry run work on a database that has not been migrated yet.
        $hasFlagColumn = Schema::hasColumn('users', 'must_change_password');

        if (! $hasFlagColumn && ! $isDryRun) {
            $this->error('The must_change_password column does not exist. Run php artisan migrate first.');

            return self::FAILURE;
        }

        $matches = [];
        $skipped = [];

        User::query()
            ->when($hasFlagColumn, fn ($query) => $query->where('must_change_password', false))
            ->chunkById(100, function ($users) use (&$matches, &$skipped, $except) {
                foreach ($users as $user) {
                    if (! Hash::check(self::RETIRED_DEFAULT, $user->password)) {
                        continue;
                    }

                    if (in_array(Str::lower($user->email), $except, true)) {
                        $skipped[] = $user->email;

                        continue;
                    }

                    $matches[] = $user->email;
                }
            });

        $verb = $isDryRun ? 'Would flag' : 'Flagging';
        $this->info("{$verb}: ".count($matches));

        foreach ($matches as $email) {
            $this->line("  {$email}");
        }

        if ($skipped !== []) {
            $this->newLine();
            $this->info('Skipped with --except: '.count($skipped));

            foreach ($skipped as $email) {
                $this->line("  {$email}");
            }
        }

        if ($isDryRun) {
            $this->newLine();
            $this->info('Dry run. Nothing was written.');

            return self::SUCCESS;
        }

        if ($matches === []) {
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Flag these accounts so they must change their password at next login?')) {
            $this->warn('Aborted. Nothing was changed.');

            return self::SUCCESS;
        }

        User::query()->whereIn('email', $matches)->update(['must_change_password' => true]);

        $this->info('Flagged '.count($matches).' account(s).');

        return self::SUCCESS;
    }
}
