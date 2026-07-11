<?php

namespace App\Attachments;

/**
 * A single named attachment slot definition (Phase 2 item 8) — e.g. "Letter
 * of Intent" on Registration, or "Photos" on After-Activity Report. Slot
 * lists are static PHP (see AttachmentSlots), not DB-seeded config: they are
 * a fixed structural fact of the client's physical form, not personnel or
 * process (invariant #1's "configuration not code" is about approval chains
 * specifically and doesn't extend here).
 */
final readonly class AttachmentSlot
{
    public function __construct(
        public string $key,
        public string $label,
        public bool $required,
        public bool $multiple = false,
    ) {}
}
