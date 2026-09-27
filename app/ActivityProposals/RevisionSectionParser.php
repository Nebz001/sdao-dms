<?php

namespace App\ActivityProposals;

use App\Approval\SectionFlags;
use App\Models\Document;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Parses the mobile app's free-text "Sections needing revision: X, Y, Z"
 * line into real SectionFlags keys, so a mobile return-for-revision
 * highlights the same sections on the web as a native web return would.
 *
 * The raw remarks are ALWAYS saved verbatim regardless of what this parses
 * — see ReviewActivityProposal/DocumentActionController — this only adds
 * structured `flagged_sections` on top when the labels are recognisable.
 *
 * Matching works on comma-separated TOKENS, not raw substring search, and
 * tries labels longest-first: one real section label — "Request Letter
 * (must include Rationale, Objectives, and Program)" — contains commas of
 * its own, so it is itself a 3-token run. Matching it whole and removing
 * exactly those 3 tokens before a shorter label like "Objectives" is ever
 * tried is what stops "Objectives" from being matched a second time out
 * of that longer label's own text.
 */
class RevisionSectionParser
{
    private const string MARKER = 'Sections needing revision:';

    /**
     * @return array<int, string> matched SectionFlags keys
     */
    public static function parse(string $remarks, Document $document): array
    {
        $line = self::extractSectionsLine($remarks);

        if ($line === null) {
            return [];
        }

        $labelsToKeys = collect(SectionFlags::for($document->form_type))
            ->mapWithKeys(fn ($flag) => [$flag->label => $flag->key]);

        $orderedLabels = $labelsToKeys->keys()
            ->sortByDesc(fn (string $label) => mb_strlen($label))
            ->values();

        $tokens = self::tokenize($line);
        $matchedKeys = [];

        foreach ($orderedLabels as $label) {
            $labelTokens = self::tokenize($label);
            $consumed = self::consumeFirstOccurrence($tokens, $labelTokens);

            if ($consumed !== null) {
                $matchedKeys[] = $labelsToKeys[$label];
                $tokens = $consumed;
            }
        }

        return $matchedKeys;
    }

    /**
     * The last "Sections needing revision:" line in the remarks, with the
     * marker itself stripped — the section line is always the LAST line
     * the mobile app appends, per the contract's example.
     */
    private static function extractSectionsLine(string $remarks): ?string
    {
        foreach (array_reverse(explode("\n", $remarks)) as $line) {
            $line = trim($line);

            if (Str::startsWith($line, self::MARKER)) {
                return trim(Str::after($line, self::MARKER));
            }
        }

        return null;
    }

    /**
     * @return Collection<int, non-empty-string>
     */
    private static function tokenize(string $value): Collection
    {
        return collect(explode(',', $value))
            ->map(fn (string $t) => trim($t))
            ->filter(fn (string $t) => $t !== '')
            ->values();
    }

    /**
     * If $needle appears as a contiguous, case-insensitive run inside
     * $haystack, returns $haystack with that first occurrence removed.
     * Returns null (haystack unchanged) when there is no match.
     *
     * @param  Collection<int, non-empty-string>  $haystack
     * @param  Collection<int, non-empty-string>  $needle
     * @return Collection<int, non-empty-string>|null
     */
    private static function consumeFirstOccurrence(Collection $haystack, Collection $needle): ?Collection
    {
        $needleLower = $needle->map(fn (string $t) => mb_strtolower($t))->all();
        $n = count($needleLower);

        if ($n === 0) {
            return null;
        }

        for ($i = 0; $i <= $haystack->count() - $n; $i++) {
            $slice = $haystack->slice($i, $n)->map(fn (string $t) => mb_strtolower($t))->values()->all();

            if ($slice === $needleLower) {
                return $haystack->slice(0, $i)->merge($haystack->slice($i + $n))->values();
            }
        }

        return null;
    }
}
