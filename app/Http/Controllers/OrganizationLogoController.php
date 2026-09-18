<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves an organization's logo. No Gate — org names and logos are
 * already-public roster information, the same rationale
 * JoinOrganizationController::search() and the partner-organization search
 * endpoint already run on; `auth`+`verified` from the route group is the
 * whole gate. Streamed rather than `Storage::url()` because the
 * `attachments` disk is a Supabase S3 disk with no public `url` key (see
 * config/filesystems.php) — mirrors AttachmentController::download(),
 * minus that Gate check.
 *
 * No upload feature exists yet (Organization::hasLogo() is always false
 * today), so this route currently only ever answers 404 — added now so the
 * shared auth.organization.logoUrl prop and the sidebar's image branch are
 * already wired for when uploads land.
 */
class OrganizationLogoController extends Controller
{
    public function __invoke(Organization $organization): StreamedResponse
    {
        abort_unless($organization->hasLogo(), 404);

        return Storage::disk($organization->logo_disk)->response($organization->logo_path);
    }
}
