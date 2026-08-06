<?php

namespace App\Printing;

use App\Models\Document;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * Renders a Document to its paper-form-matching PDF via the PrintableForms
 * registry. One eager-load per implementation's own eagerLoads() keeps this
 * generic across every form type without hardcoding relations here.
 */
class RenderDocumentPdf
{
    public function execute(Document $document, PrintableForm $form): DomPdf
    {
        $document->loadMissing($form->eagerLoads());

        return Pdf::loadView($form->view(), $form->data($document))
            ->setPaper($form->paper(), $form->orientation());
    }
}
