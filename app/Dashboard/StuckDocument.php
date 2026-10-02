<?php

namespace App\Dashboard;

use App\Enums\Role;
use App\Models\Document;

/**
 * One in-review document as the admin dashboard sees it: who it is waiting
 * on, and how long it has been idle. Built only by InReviewSnapshot.
 */
final readonly class StuckDocument
{
    public function __construct(
        public Document $document,
        public Role $stepRole,
        /** Stable grouping key: "sdao", "user:{id}", or "unassigned:{role}:{scope}". */
        public string $approverKey,
        public string $approverName,
        /** e.g. "Program Chair, School of Architecture, Computing, and Engineering". */
        public string $approverLine,
        public int $idleDays,
        /** 'fresh' | 'aging' | 'stale' — see InReviewSnapshot::tierFor(). */
        public string $tier,
    ) {}

    public function isSdaoStep(): bool
    {
        return $this->stepRole === Role::SdaoMember;
    }
}
