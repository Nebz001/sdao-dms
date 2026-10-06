<?php

use App\Support\PersonName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Adds first_name / last_name and backfills them from the existing `name`.
 *
 * Deliberately non-destructive and safe to run on live data:
 *  - the columns are nullable here (a NOT NULL add would fail on existing
 *    rows; "required" is enforced by validation for new and updated accounts);
 *  - `name` is never dropped. It is only edited for a row that has a
 *    parenthesised note in it ("Maria C. Evangelista (Associate Dean)"): the
 *    note is removed so name, first_name and last_name agree, and what was
 *    removed is logged. Titles stay in `name` (so display and printouts do
 *    not change) but are kept out of first_name;
 *  - only rows with neither first_name nor last_name set are touched, so a
 *    re-run is a no-op and a hand-corrected row is never overwritten;
 *  - plain query-builder calls only, so it behaves the same on Postgres and
 *    SQLite, and a row it cannot split is left alone rather than failing the
 *    deploy.
 * Splits it is unsure about are written to the log for a hand check.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'first_name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('first_name')->nullable()->after('name');
            });
        }

        if (! Schema::hasColumn('users', 'last_name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('last_name')->nullable()->after('first_name');
            });
        }

        $unsure = [];
        $cleaned = [];

        DB::table('users')
            ->whereNull('first_name')
            ->whereNull('last_name')
            ->orderBy('id')
            ->select(['id', 'name'])
            ->chunkById(200, function ($users) use (&$unsure, &$cleaned) {
                foreach ($users as $user) {
                    $parts = PersonName::split((string) $user->name);

                    if ($parts['first'] === '') {
                        continue;
                    }

                    $changes = [
                        'first_name' => $parts['first'],
                        'last_name' => $parts['last'] === '' ? null : $parts['last'],
                    ];

                    $cleanName = PersonName::stripNote(PersonName::clean((string) $user->name));

                    if ($cleanName !== PersonName::clean((string) $user->name)) {
                        $changes['name'] = $cleanName;
                        $cleaned[] = "#{$user->id} \"{$user->name}\" => \"{$cleanName}\"";
                    }

                    DB::table('users')->where('id', $user->id)->update($changes);

                    if (! $parts['confident']) {
                        $unsure[] = "#{$user->id} \"{$user->name}\" => first \"{$parts['first']}\", last \"{$parts['last']}\"";
                    }
                }
            });

        if ($unsure !== [] || $cleaned !== []) {
            try {
                if ($cleaned !== []) {
                    Log::info('users first/last name backfill: removed parenthesised notes from name', ['users' => $cleaned]);
                }

                if ($unsure !== []) {
                    Log::warning('users first/last name backfill: check these splits by hand', ['users' => $unsure]);
                }
            } catch (Throwable) {
                // Logging must never fail a deploy.
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
