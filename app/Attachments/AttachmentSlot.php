<?php

namespace App\Attachments;

/**
 * A single named attachment slot definition (Phase 2 item 8) — e.g. "Letter
 * of Intent" on Registration, or "Photos" on After-Activity Report. Slot
 * lists are static PHP (see AttachmentSlots), not DB-seeded config: they are
 * a fixed structural fact of the client's physical form, not personnel or
 * process (invariant #1's "configuration not code" is about approval chains
 * specifically and doesn't extend here).
 *
 * $step (Group C item 3) — which submission step collects this slot.
 * Defaults to 1: every form type except Activity Proposal is single-step, so
 * this is a no-op for them. Activity Proposal is the first (and so far only)
 * form type where this matters — its 3 slots are collected at step 1, and
 * $step lets AttachmentSlots::for() scope a step-1-only vs step-2-only view
 * without a second, hand-written slot list to keep in sync.
 */
final readonly class AttachmentSlot
{
    public function __construct(
        public string $key,
        public string $label,
        public bool $required,
        public bool $multiple = false,
        public int $step = 1,
    ) {}
}
