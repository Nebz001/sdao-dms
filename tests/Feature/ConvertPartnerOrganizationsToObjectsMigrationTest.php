<?php

use App\Models\ActivityProposal;
use App\Models\Document;
use Illuminate\Support\Facades\DB;

/**
 * Regression coverage for the 2026_09_17_110000 migration. partner_organizations
 * changed from array<string> to array<{organization_id, name}> so a searchable
 * combobox with a free-text fallback can record which entries reference a real
 * organizations row. Existing rows only ever held plain strings; this migration
 * backfills them losslessly to {organization_id: null, name: <string>} — every
 * pre-migration entry was free text by definition, since nothing before this
 * change ever linked an entry to an organization.
 */
function runConvertPartnerOrganizationsToObjectsMigration(): void
{
    (require database_path('migrations/2026_09_17_110000_convert_activity_proposals_partner_organizations_to_objects.php'))->up();
}

test('a plain-string partner_organizations row is converted to {organization_id: null, name} objects', function () {
    $document = Document::factory()->create();
    $proposal = ActivityProposal::factory()->create(['document_id' => $document->id]);

    // Write the OLD shape directly, bypassing the model's array cast (which
    // would json_encode a plain array either way, but this makes the
    // pre-migration shape explicit rather than incidental).
    DB::table('activity_proposals')->where('id', $proposal->id)
        ->update(['partner_organizations' => json_encode(['Partner A', 'Partner B'])]);

    runConvertPartnerOrganizationsToObjectsMigration();

    expect($proposal->fresh()->partner_organizations)->toBe([
        ['organization_id' => null, 'name' => 'Partner A'],
        ['organization_id' => null, 'name' => 'Partner B'],
    ]);
});

test('running the migration twice does not double-wrap an already-converted row', function () {
    $document = Document::factory()->create();
    $proposal = ActivityProposal::factory()->create(['document_id' => $document->id]);

    DB::table('activity_proposals')->where('id', $proposal->id)
        ->update(['partner_organizations' => json_encode(['Partner A'])]);

    runConvertPartnerOrganizationsToObjectsMigration();
    runConvertPartnerOrganizationsToObjectsMigration();

    expect($proposal->fresh()->partner_organizations)->toBe([
        ['organization_id' => null, 'name' => 'Partner A'],
    ]);
});

test('a row already storing {organization_id, name} objects is left untouched', function () {
    $document = Document::factory()->create();
    $proposal = ActivityProposal::factory()->create([
        'document_id' => $document->id,
        'partner_organizations' => [
            ['organization_id' => 5, 'name' => 'Computing Society'],
            ['organization_id' => null, 'name' => 'External School'],
        ],
    ]);

    runConvertPartnerOrganizationsToObjectsMigration();

    expect($proposal->fresh()->partner_organizations)->toBe([
        ['organization_id' => 5, 'name' => 'Computing Society'],
        ['organization_id' => null, 'name' => 'External School'],
    ]);
});

test('a null partner_organizations row is left null', function () {
    $document = Document::factory()->create();
    $proposal = ActivityProposal::factory()->create([
        'document_id' => $document->id,
        'partner_organizations' => null,
    ]);

    runConvertPartnerOrganizationsToObjectsMigration();

    expect($proposal->fresh()->partner_organizations)->toBeNull();
});
