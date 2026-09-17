<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Group E — Partner Organization(s)/School(s)/RSO becomes a searchable
     * combobox with a free-text fallback. The stored shape changes from a
     * flat array of strings to an array of {organization_id, name} objects
     * so it's clear afterward which entries reference a real organizations
     * row and which are free text — the column stays `json`, only the shape
     * of its elements changes, so this is a plain reshape, not a column-type
     * migration (contrast 2026_09_10_145843_convert_activity_proposals_
     * target_sdg_to_multi_select, which needed an add/backfill/drop/rename
     * dance because THAT column wasn't JSON-typed to begin with).
     *
     * Every existing element is a plain string; each becomes
     * {"organization_id": null, "name": "<that string>"} — a lossless,
     * truthful backfill, since nothing before this migration ever linked an
     * entry to an organizations row. Idempotent: an element that's already
     * an array (a rerun, or a row written after this shipped) is left alone.
     *
     * document_transitions.field_changes is deliberately NOT touched here —
     * it stores pre-formatted display strings frozen at snapshot time
     * (FieldChangeSet -> FieldValueFormatter::format()), never the raw
     * array, so there is nothing in that historical audit trail for this
     * migration to reshape.
     */
    public function up(): void
    {
        DB::table('activity_proposals')
            ->whereNotNull('partner_organizations')
            ->orderBy('id')
            ->select('id', 'partner_organizations')
            ->each(function (object $row): void {
                $items = json_decode($row->partner_organizations, true);

                if (! is_array($items)) {
                    return;
                }

                $converted = array_map(
                    fn ($item) => is_array($item) ? $item : ['organization_id' => null, 'name' => (string) $item],
                    $items,
                );

                DB::table('activity_proposals')
                    ->where('id', $row->id)
                    ->update(['partner_organizations' => json_encode($converted)]);
            });
    }

    /**
     * Deliberately a no-op — a linked entry's organization_id has no
     * lossless reverse mapping back to "just a string", and even a free-text
     * entry's {organization_id: null, name} would just be re-flattening
     * data this migration didn't lose in the first place. Not reversible.
     */
    public function down(): void
    {
        //
    }
};
