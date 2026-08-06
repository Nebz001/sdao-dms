<?php

namespace App\Printing;

/**
 * One endorsement column on the printed Student Organization Application
 * Form (§3 Endorsements / §4 Received by) — Faculty Adviser, College Dean,
 * SDAO, or CRSO. `names` holds zero, one, or two signatories (SDAO requires
 * both members, invariant #3); the others hold at most one. `date`/`time`
 * are pre-formatted strings (Asia/Manila) or null when nothing to show.
 */
final readonly class EndorsementColumn
{
    /**
     * @param  array<int, string>  $names
     */
    public function __construct(
        public array $names,
        public ?string $date,
        public ?string $time,
    ) {}

    public static function blank(): self
    {
        return new self(names: [], date: null, time: null);
    }
}
