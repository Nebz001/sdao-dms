<?php

namespace App\Printing;

use App\Models\Document;

/**
 * A PrintableForm that embeds uploaded files: extra view data that needs
 * storage reads (kept out of data() so that stays cheap and pure), plus
 * files appended as pages after the main form.
 */
interface HasPrintAppendices
{
    /**
     * Extra Blade data that requires reading attachment bytes.
     *
     * @return array<string, mixed>
     */
    public function assets(Document $document): array;

    /**
     * @return array<int, PrintAppendix>
     */
    public function appendices(Document $document): array;
}
