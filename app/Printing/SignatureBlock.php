<?php

namespace App\Printing;

/**
 * One signature block on a printed form whose approval-chain roles vary by
 * form variant (Activity Proposal, and any future form that needs the same
 * shape) — generalizes App\Printing\EndorsementColumn with a role caption,
 * since these forms print a different set of roles per variant (regular vs
 * SHS, on- vs off-calendar) rather than the pilot's fixed column headers.
 * `names` holds zero, one, or two signatories (SDAO requires both members,
 * invariant #3); every other role holds at most one. `date`/`time` are
 * pre-formatted strings (Asia/Manila) or null when nothing to show.
 */
final readonly class SignatureBlock
{
    /**
     * @param  array<int, string>  $names
     */
    public function __construct(
        public string $roleLabel,
        public array $names,
        public ?string $date,
        public ?string $time,
    ) {}

    public static function blank(string $roleLabel): self
    {
        return new self(roleLabel: $roleLabel, names: [], date: null, time: null);
    }
}
