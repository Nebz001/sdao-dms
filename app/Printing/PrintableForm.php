<?php

namespace App\Printing;

use App\Models\Document;

/**
 * A printable, paper-form-matching representation of a Document — the
 * printing counterpart to App\Attachments\AttachmentSlot: one implementation
 * per form type, registered in PrintableForms.
 */
interface PrintableForm
{
    /**
     * Blade view name (dot notation) that renders this form.
     */
    public function view(): string;

    /**
     * dompdf paper size, e.g. 'a4'.
     */
    public function paper(): string;

    /**
     * dompdf orientation: 'portrait' or 'landscape'.
     */
    public function orientation(): string;

    /**
     * Download/inline filename, without extension.
     */
    public function filename(Document $document): string;

    /**
     * Relations RenderDocumentPdf must eager-load before calling data(), so
     * data() never triggers N+1 queries.
     *
     * @return array<int, string>
     */
    public function eagerLoads(): array;

    /**
     * All data the Blade view needs, fully derived — the view stays dumb.
     *
     * @return array<string, mixed>
     */
    public function data(Document $document): array;
}
