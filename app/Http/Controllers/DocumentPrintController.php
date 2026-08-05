<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Printing\PrintableForms;
use App\Printing\RenderDocumentPdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Generic printable-form endpoint shared by every form type (Phase 2 —
 * printable official forms), mirroring AttachmentController::download()'s
 * cross-form-type shape. Deliberately allowed for non-terminal documents:
 * the printed form IS the routing artifact a student carries for wet
 * signatures, which by definition happens before approval — the artifact
 * never falsely implies approval since every approval-bearing field is
 * derived from real data (see OrganizationApplicationForm).
 */
class DocumentPrintController extends Controller
{
    public function __invoke(Document $document, RenderDocumentPdf $render): Response
    {
        if (! Gate::allows('view', $document) && ! Gate::allows('reviewView', $document)) {
            abort(403);
        }

        $form = PrintableForms::for($document->form_type);

        abort_unless($form !== null, 404);

        return $render->execute($document, $form)->stream($form->filename($document).'.pdf');
    }
}
