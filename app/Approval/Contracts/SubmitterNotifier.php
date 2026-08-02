<?php

namespace App\Approval\Contracts;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;

/**
 * Contract for the student-facing outcome notification trigger.
 *
 * The engine calls this when a document reaches Approved (final quorum),
 * Rejected, or Returned — the three outcomes a student cannot otherwise see
 * without opening the page. Unlike ApproverNotifier (fired once per step
 * activation, for every approver at that step), this fires once per outcome,
 * per recipient — ApprovalEngine::notifySubmitter() calls it once for each of
 * the org's active officers (president AND secretary — equal partners, per
 * CLAUDE.md), falling back to the original submitter alone (Document::submitted_by)
 * only when the org has no active officers yet (the founding-registration
 * edge case).
 */
interface SubmitterNotifier
{
    public function notify(User $recipient, Document $document, DocumentStatus $outcome, ?string $comment = null): void;
}
