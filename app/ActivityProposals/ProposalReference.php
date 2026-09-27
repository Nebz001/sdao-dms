<?php

namespace App\ActivityProposals;

use App\Enums\FormType;
use App\Models\Document;
use App\Support\DisplayTimezone;
use Illuminate\Support\Str;

/**
 * The mobile app's display id for an Activity Proposal — "PROP-2026-057" —
 * derived from the document's real primary key, with no new column and no
 * per-year sequence. The number is the global document id; it never
 * changes and is never reused.
 */
class ProposalReference
{
    private const string PREFIX = 'PROP';

    public static function format(Document $document): string
    {
        $year = DisplayTimezone::convert($document->created_at)->format('Y');

        return sprintf('%s-%s-%03d', self::PREFIX, $year, $document->id);
    }

    /**
     * Resolves a reference string back to its Activity Proposal document.
     * Returns null for anything that isn't an exact match for what
     * format() would itself produce — malformed, unknown id, wrong year,
     * non-canonical zero-padding, or a document that isn't an Activity
     * Proposal — so the caller can turn every one of those cases into the
     * same 404, without a second, separately-maintained set of rules.
     */
    public static function resolve(string $reference): ?Document
    {
        if (! preg_match('/^PROP-\d{4}-\d+$/', $reference)) {
            return null;
        }

        $id = (int) Str::afterLast($reference, '-');

        if ($id <= 0) {
            return null;
        }

        $document = Document::query()
            ->where('id', $id)
            ->where('form_type', FormType::ActivityProposal->value)
            ->first();

        if ($document === null || self::format($document) !== $reference) {
            return null;
        }

        return $document;
    }
}
