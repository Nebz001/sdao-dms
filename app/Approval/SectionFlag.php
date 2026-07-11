<?php

namespace App\Approval;

/**
 * A single flaggable section definition (Phase 2 item 9) — e.g. "Contact
 * Information" on Registration/Renewal. Section lists are static PHP (see
 * SectionFlags), sourced verbatim from sdao.md's per-form section
 * definitions, mirroring App\Attachments\AttachmentSlot's shape.
 */
final readonly class SectionFlag
{
    public function __construct(
        public string $key,
        public string $label,
    ) {}
}
