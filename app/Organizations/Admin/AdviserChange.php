<?php

namespace App\Organizations\Admin;

use App\Enums\AdviserTermOutcome;
use App\Models\Organization;
use App\Models\User;

/**
 * What a committed adviser swap did, handed to AdviserChangeNotifier once the
 * transaction (including any outer one) has committed.
 */
final readonly class AdviserChange
{
    public function __construct(
        public Organization $organization,
        public User $incoming,
        public ?User $outgoing,
        public ?AdviserTermOutcome $outgoingOutcome,
    ) {}
}
