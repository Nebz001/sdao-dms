<?php

namespace App\Printing;

use App\Models\DocumentAttachment;

/**
 * One uploaded file printed after the main form, under its own heading
 * (e.g. "Appendix A: Sample Evaluation Form").
 */
final readonly class PrintAppendix
{
    public function __construct(
        public string $letter,
        public string $title,
        public DocumentAttachment $attachment,
    ) {}
}
