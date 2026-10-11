<?php

namespace App\Printing;

use App\Models\Document;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Throwable;

/**
 * Renders a Document to its paper-form-matching PDF via the PrintableForms
 * registry. One eager-load per implementation's own eagerLoads() keeps this
 * generic across every form type without hardcoding relations here.
 *
 * A form that implements HasPrintAppendices also gets its uploaded files
 * appended after the main form. dompdf cannot render PDFs, so each appendix
 * is rendered on its own (heading page, plus the image if it is one) and the
 * segments — including the uploaded PDFs' real pages — are merged with FPDI.
 */
class RenderDocumentPdf
{
    /** Image box for an appendix page, below its heading (mm). */
    private const float APPENDIX_MAX_WIDTH_MM = 190;

    private const float APPENDIX_MAX_HEIGHT_MM = 225;

    public function __construct(private PrintAttachmentLoader $loader) {}

    public function execute(Document $document, PrintableForm $form): DomPdf
    {
        $document->loadMissing($form->eagerLoads());

        $data = $form->data($document);

        if ($form instanceof HasPrintAppendices) {
            $data += $form->assets($document);
        }

        return Pdf::loadView($form->view(), $data)
            ->setPaper($form->paper(), $form->orientation());
    }

    /**
     * The finished PDF bytes: the main form, then each appendix in order.
     */
    public function output(Document $document, PrintableForm $form): string
    {
        $main = $this->execute($document, $form)->output();

        if (! $form instanceof HasPrintAppendices) {
            return $main;
        }

        $segments = [$main];

        foreach ($form->appendices($document) as $appendix) {
            $file = $this->loader->resolve(
                $appendix->attachment,
                self::APPENDIX_MAX_WIDTH_MM,
                self::APPENDIX_MAX_HEIGHT_MM,
                upscale: true,
            );

            $segments[] = Pdf::loadView('print.appendix', [
                'letter' => $appendix->letter,
                'title' => $appendix->title,
                'file' => $file,
            ])->setPaper($form->paper(), $form->orientation())->output();

            if ($file['kind'] === 'pdf') {
                $segments[] = $file['bytes'];
            }
        }

        if (count($segments) === 1) {
            return $main;
        }

        try {
            return $this->merge($segments);
        } catch (Throwable $e) {
            Log::error('Print: appendix merge failed, serving the main form only.', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            return $main;
        }
    }

    /**
     * @param  array<int, string>  $segments  PDF byte strings, in page order
     */
    private function merge(array $segments): string
    {
        $merged = new Fpdi;

        foreach ($segments as $bytes) {
            $pages = $merged->setSourceFile(StreamReader::createByString($bytes));

            for ($page = 1; $page <= $pages; $page++) {
                $template = $merged->importPage($page);
                $size = $merged->getTemplateSize($template);

                $merged->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $merged->useTemplate($template);
            }
        }

        return $merged->Output('S');
    }
}
