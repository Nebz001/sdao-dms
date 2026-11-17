<?php

namespace App\Support;

/**
 * The one place a "one objective per line" text value is split into items.
 *
 * Specific Objectives is stored as plain text with newlines (never JSON, so
 * the revision history and section flagging keep working on a string) and is
 * numbered only when displayed. The print form, the mobile resource and the
 * validation rule all go through here; resources/js/lib/proposal-text.ts
 * (`splitSpecificObjectives`) mirrors it for the web pages.
 *
 * Splitting trims every line, drops blank ones and keeps the author's order.
 * A leading list marker the author typed themselves ("1.", "2)", "-", "•",
 * "*" followed by a space) is dropped so the displayed number is not doubled.
 */
final class SpecificObjectives
{
    private const string LIST_MARKER = '/^(?:\d+\s*[.)]|[-•*])\s+/u';

    /**
     * Line breaks as plain "\n". A browser form posts a textarea with "\r\n",
     * which would count twice against the length cap the page counts once, and
     * would make the same text compare as changed against an autosaved copy.
     */
    public static function normalize(string $text): string
    {
        return str_replace(["\r\n", "\r"], "\n", $text);
    }

    /**
     * @return list<string>
     */
    public static function split(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        $items = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $item = trim((string) preg_replace(self::LIST_MARKER, '', trim($line)));

            if ($item !== '') {
                $items[] = $item;
            }
        }

        return $items;
    }

    public static function hasAny(?string $text): bool
    {
        return self::split($text) !== [];
    }
}
