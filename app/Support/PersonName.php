<?php

namespace App\Support;

/**
 * Splits a single "full name" string into first and last name, and joins them
 * back. Used by the users first_name/last_name backfill migration and by the
 * User model's legacy `name` fallback.
 *
 * Filipino names routinely have two given names, a middle initial and
 * multi-word surnames, so "last word is the last name" is only the default:
 * surname particles (dela, de, del, delos, de los, de la, san, sta, santa)
 * stay with the surname, and a middle initial stays with the first name.
 * Honorific titles (Mr., Dr., Engr., ...) and parenthesised notes are not part
 * of a name and never end up in first_name or last_name. Anything this cannot
 * be sure about is reported as `confident: false` so a person can check it by
 * hand.
 */
final class PersonName
{
    /** Particles that always belong to the surname that follows them. */
    private const array PARTICLES = ['dela', 'de', 'del', 'delos', 'san', 'sta', 'santa'];

    /** "de los" / "de la" / "de las": the second word is a particle only after "de". */
    private const array ARTICLES_AFTER_DE = ['los', 'la', 'las'];

    /** Honorific titles. Only ever removed from the START of a name; a trailing "Sr." is a suffix. */
    private const array TITLES = ['mr', 'ms', 'mrs', 'miss', 'dr', 'engr', 'atty', 'prof', 'fr', 'sr', 'ar', 'sir'];

    private const array SUFFIXES = ['jr', 'sr', 'ii', 'iii', 'iv', 'v'];

    /**
     * `title` is the removed honorific(s), e.g. "Dr."; titles never end up in `first`.
     *
     * @return array{first: string, last: string, confident: bool, title: string}
     */
    public static function split(string $fullName): array
    {
        $fullName = self::clean($fullName);
        $withoutNote = self::stripNote($fullName);
        $hadNote = $withoutNote !== $fullName;

        [$title, $fullName] = self::extractTitle($withoutNote);

        if ($fullName === '') {
            return ['first' => '', 'last' => '', 'confident' => false, 'title' => $title];
        }

        // "Dela Cruz, Juan" — everything before the comma is the surname.
        if (str_contains($fullName, ',')) {
            [$last, $first] = array_map(self::clean(...), explode(',', $fullName, 2));

            if ($first !== '' && $last !== '') {
                return ['first' => self::stripTitle($first), 'last' => $last, 'confident' => false, 'title' => $title];
            }

            $fullName = self::clean(str_replace(',', ' ', $fullName));
        }

        $tokens = explode(' ', $fullName);

        if (count($tokens) === 1) {
            return ['first' => $tokens[0], 'last' => '', 'confident' => false, 'title' => $title];
        }

        $suffix = null;

        if (count($tokens) > 2 && in_array(self::normalize(end($tokens)), self::SUFFIXES, true)) {
            $suffix = array_pop($tokens);
        }

        $start = count($tokens) - 1;
        $usedParticle = false;

        while ($start > 1) {
            $previous = self::normalize($tokens[$start - 1]);

            if (in_array($previous, self::PARTICLES, true)) {
                $start--;
                $usedParticle = true;
            } elseif (
                in_array($previous, self::ARTICLES_AFTER_DE, true)
                && $start > 2
                && self::normalize($tokens[$start - 2]) === 'de'
            ) {
                $start -= 2;
                $usedParticle = true;
            } else {
                break;
            }
        }

        $first = implode(' ', array_slice($tokens, 0, $start));
        $last = implode(' ', array_slice($tokens, $start));

        if ($suffix !== null) {
            $last .= ' '.$suffix;
        }

        return [
            'first' => $first,
            'last' => $last,
            'confident' => ! $usedParticle && $suffix === null && ! $hadNote && count($tokens) <= 4,
            'title' => $title,
        ];
    }

    /**
     * The users-table name columns for a full name, e.g. for a seeder that is
     * handed "Dr. Pia Jasmin I. Quizon". `name` keeps the full name exactly as
     * given (title included, so what is displayed does not change); first_name
     * and last_name never hold the title. Includes `name` itself so it works
     * with model events off (seeders use WithoutModelEvents, which skips the
     * User saving hook that normally keeps `name` in sync).
     *
     * @return array{name: string, first_name: string, last_name: string|null}
     */
    public static function attributes(string $fullName): array
    {
        $parts = self::split($fullName);

        return [
            'name' => self::clean($fullName),
            'first_name' => $parts['first'],
            'last_name' => $parts['last'] === '' ? null : $parts['last'],
        ];
    }

    /**
     * "First Last", skipping whichever part is missing.
     */
    public static function join(?string $first, ?string $last): string
    {
        return self::clean(trim((string) $first).' '.trim((string) $last));
    }

    /**
     * Trims and collapses runs of whitespace (including non-breaking spaces).
     */
    public static function clean(string $value): string
    {
        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $value));
    }

    /**
     * Removes every parenthesised note, e.g. "Maria C. Evangelista (Associate Dean)".
     */
    public static function stripNote(string $value): string
    {
        return self::clean((string) preg_replace('/\([^)]*\)/u', ' ', $value));
    }

    /**
     * The honorific(s) a name starts with, as written ("Dr.", "Engr. Atty."), or ''.
     */
    public static function leadingTitle(string $name): string
    {
        return self::extractTitle(self::stripNote($name))[0];
    }

    /**
     * A first-name input without any leading honorific. Left as typed when it
     * is nothing but titles.
     */
    public static function stripTitle(string $firstName): string
    {
        $firstName = self::clean($firstName);
        $rest = self::extractTitle($firstName)[1];

        return $rest === '' ? $firstName : $rest;
    }

    /**
     * @return array{0: string, 1: string} [titles, the rest]; the rest is never empty unless the input is.
     */
    private static function extractTitle(string $name): array
    {
        $tokens = $name === '' ? [] : explode(' ', $name);
        $titles = [];

        while (count($tokens) > 1 && in_array(self::normalize($tokens[0]), self::TITLES, true)) {
            $titles[] = array_shift($tokens);
        }

        return [implode(' ', $titles), implode(' ', $tokens)];
    }

    private static function normalize(string $token): string
    {
        return mb_strtolower(rtrim($token, '.'));
    }
}
