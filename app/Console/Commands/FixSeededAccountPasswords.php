<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * One-off fix for a specific past bug: IdentitySeeder's `user()` helper (and
 * MembershipSeeder's one extra account) created these placeholder demo/staff
 * accounts without an explicit password, silently falling back to
 * UserFactory's default ("password") instead of the intended "ict@1234"
 * (the convention every other seeder — RealRosterSeeder, DemoDataSeeder —
 * already followed). That's fixed at the seeder level now, but any
 * environment that seeded BEFORE the fix (e.g. a production database
 * already seeded via the old code, where a destructive reseed is out of the
 * question) still has the wrong password baked into these rows. This
 * command re-hashes exactly those rows in place, without touching anything
 * else — no organizations, documents, notifications, or any other user.
 *
 * Deliberately scoped to a hardcoded email => expected-name allow-list,
 * never a heuristic like "any account whose password matches some guessed
 * string." That heuristic is unsafe in production: a real self-registered
 * student's genuinely CHOSEN password could legitimately BE "password" (it
 * passes the current 8-character-minimum policy), and blindly overwriting
 * it would silently change a real user's credentials without their
 * consent. Matching on the exact known placeholder email AND name, and
 * skipping (loudly) on any mismatch, means this can never touch an account
 * it wasn't specifically built to fix.
 *
 * Idempotent and safe to re-run: an already-correct row is left untouched
 * (and reported as such), a missing email is skipped, and a name mismatch is
 * skipped with a loud warning rather than silently overwritten.
 */
class FixSeededAccountPasswords extends Command
{
    private const string PASSWORD = 'ict@1234';

    /** @var array<string, string> email => the exact name IdentitySeeder/MembershipSeeder set */
    private const array ACCOUNTS = [
        'sdao-a@nu-lipa.edu.ph' => 'SDAO Member A',
        'sdao-b@nu-lipa.edu.ph' => 'SDAO Member B',
        'asst-director@nu-lipa.edu.ph' => 'Asst. Director of Academic Services',
        'academic-director@nu-lipa.edu.ph' => 'Academic Director',
        'executive-director@nu-lipa.edu.ph' => 'Executive Director',
        'dean-ccit@nu-lipa.edu.ph' => 'Dean CCIT',
        'chair-cs@nu-lipa.edu.ph' => 'Chair CS',
        'adviser-one@nu-lipa.edu.ph' => 'Adviser One',
        'student-alpha@students.nu-lipa.edu.ph' => 'Student Alpha',
        'chair-it@nu-lipa.edu.ph' => 'Chair IT',
        'adviser-two@nu-lipa.edu.ph' => 'Adviser Two',
        'student-beta@students.nu-lipa.edu.ph' => 'Student Beta',
        'principal-shs@nu-lipa.edu.ph' => 'Principal SHS',
        'adviser-shs@nu-lipa.edu.ph' => 'Adviser SHS',
        'student-gamma@students.nu-lipa.edu.ph' => 'Student Gamma',
        'student-delta@students.nu-lipa.edu.ph' => 'Student Delta',
    ];

    protected $signature = 'accounts:fix-seeded-passwords
        {--dry-run : Report what would change without writing anything}
        {--force : Skip the confirmation prompt}';

    protected $description = 'One-off fix: re-hash the intended ict@1234 password for the specific IdentitySeeder/MembershipSeeder placeholder accounts seeded before that bug was fixed. Touches nothing else.';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        $this->info('Accounts checked: '.count(self::ACCOUNTS).' (hardcoded email+name allow-list — see class docblock)');
        $this->line('  - environment: '.app()->environment());
        $this->newLine();

        if (! $isDryRun && ! $this->option('force') && ! $this->confirm(
            'This re-hashes the password to "ict@1234" for exactly the known IdentitySeeder/MembershipSeeder '.
            'placeholder accounts (matched by exact email AND name — anything else is skipped, never guessed). '.
            'No other data is touched. Continue?'
        )) {
            $this->warn('Aborted — nothing was changed.');

            return self::SUCCESS;
        }

        $hash = Hash::make(self::PASSWORD);
        $fixed = [];
        $alreadyCorrect = [];
        $missing = [];
        $nameMismatch = [];

        foreach (self::ACCOUNTS as $email => $expectedName) {
            $user = User::where('email', $email)->first();

            if (! $user) {
                $missing[] = $email;

                continue;
            }

            if ($user->name !== $expectedName) {
                $nameMismatch[] = "{$email} (found name \"{$user->name}\", expected \"{$expectedName}\")";

                continue;
            }

            if (Hash::check(self::PASSWORD, $user->password)) {
                $alreadyCorrect[] = $email;

                continue;
            }

            if ($isDryRun) {
                $fixed[] = $email;

                continue;
            }

            $user->forceFill(['password' => $hash])->save();
            $fixed[] = $email;
        }

        $this->printSummary($isDryRun, $fixed, $alreadyCorrect, $missing, $nameMismatch);

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $fixed
     * @param  array<int, string>  $alreadyCorrect
     * @param  array<int, string>  $missing
     * @param  array<int, string>  $nameMismatch
     */
    private function printSummary(bool $isDryRun, array $fixed, array $alreadyCorrect, array $missing, array $nameMismatch): void
    {
        $verb = $isDryRun ? 'Would fix' : 'Fixed';
        $this->info("{$verb}: ".count($fixed));

        foreach ($fixed as $email) {
            $this->line("  - {$email}");
        }

        $this->info('Already correct (no-op): '.count($alreadyCorrect));

        if ($missing !== []) {
            $this->newLine();
            $this->warn('Not found in this database (skipped, no error): '.implode(', ', $missing));
        }

        if ($nameMismatch !== []) {
            $this->newLine();
            $this->error('Name mismatch — SKIPPED for safety, nothing written (investigate before touching manually):');

            foreach ($nameMismatch as $line) {
                $this->line("  - {$line}");
            }
        }

        if ($isDryRun) {
            $this->newLine();
            $this->info('Dry run — nothing was written. Re-run without --dry-run to apply.');
        }
    }
}
