<?php

namespace App\Registrations;

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\OfficerPosition;
use App\Enums\Role;
use App\Models\Document;
use App\Models\OrganizationMembership;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Support\AcademicYear;
use Illuminate\Validation\ValidationException;

/**
 * Wraps ApprovalEngine::approve() with the founding-flow side effects
 * (Phase 2 item 5): a race-condition re-check on the chosen adviser (same
 * defensive pattern as VenueConflictChecker's approve-time re-check from
 * Slice 3 — two students could have picked the same then-available adviser
 * while both proposals were pending), and — only once the SDAO quorum is
 * actually satisfied — binding the adviser and the founding student as
 * President for the first time.
 */
class ApproveOrganizationRegistration
{
    public function __construct(private readonly ApprovalEngine $engine) {}

    /** @throws ValidationException if the chosen adviser is no longer available */
    public function execute(Document $document, User $actor): Document
    {
        $document->loadMissing('registrationDetail');
        $adviserId = $document->registrationDetail->adviser_id;

        $adviserAssignment = RoleAssignment::query()
            ->where('user_id', $adviserId)
            ->where('role', Role::Adviser->value)
            ->first();

        // Race-condition re-check: another registration may have bound this
        // same adviser to a different org since this one was submitted.
        if ($adviserAssignment !== null
            && $adviserAssignment->organization_id !== null
            && $adviserAssignment->organization_id !== $document->organization_id) {
            throw ValidationException::withMessages([
                'approve' => 'Cannot approve: the chosen adviser is now assigned to a different organization. Return the document so the student can pick a different adviser.',
            ]);
        }

        $this->engine->approve($document, $actor);
        $document->refresh();

        // Only bind once the SDAO quorum is actually satisfied (both
        // members) — the first of two approvals does not yet flip status to
        // Approved, and binding must not happen prematurely.
        if ($document->status === DocumentStatus::Approved) {
            $adviserAssignment?->update(['organization_id' => $document->organization_id]);

            OrganizationMembership::create([
                'user_id' => $document->submitted_by,
                'organization_id' => $document->organization_id,
                'position' => OfficerPosition::President->value,
                'academic_year' => AcademicYear::current(),
                'is_active' => true,
            ]);
        }

        return $document;
    }
}
