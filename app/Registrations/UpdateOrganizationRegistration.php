<?php

namespace App\Registrations;

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\OrganizationType;
use App\Enums\Role;
use App\Models\Document;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateOrganizationRegistration
{
    public function __construct(private readonly ApprovalEngine $engine) {}

    /**
     * @param  array<int, string>|null  $roster
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(
        User $actor,
        Document $document,
        OrganizationType $organizationType,
        string $description,
        string $contactPerson,
        string $contactNumber,
        string $contactEmail,
        string $dateOrganized,
        ?array $roster = null,
        ?int $adviserId = null,
    ): Document {
        if ($document->status !== DocumentStatus::Returned) {
            throw new AuthorizationException('Only returned documents can be edited.');
        }

        if ($document->submitted_by !== $actor->id) {
            throw new AuthorizationException('Only the original submitter may edit this document.');
        }

        return DB::transaction(function () use (
            $actor, $document, $organizationType, $description,
            $contactPerson, $contactNumber, $contactEmail, $dateOrganized, $roster, $adviserId
        ) {
            $updates = [
                'organization_type' => $organizationType->value,
                'description' => $description,
                'contact_person' => $contactPerson,
                'contact_number' => $contactNumber,
                'contact_email' => $contactEmail,
                'date_organized' => $dateOrganized,
                'roster' => $roster,
            ];

            // Return-for-revision preserves the ability to pick a DIFFERENT
            // adviser (Phase 2 item 5) — e.g. SDAO's only issue was the
            // chosen adviser. Re-validated as a real, admin-provisioned
            // adviser account, same as at initial proposal.
            if ($adviserId !== null) {
                $isAdviser = RoleAssignment::query()
                    ->where('user_id', $adviserId)
                    ->where('role', Role::Adviser->value)
                    ->exists();

                if (! $isAdviser) {
                    throw ValidationException::withMessages([
                        'adviser_id' => 'Choose an adviser from the list of admin-provisioned adviser accounts.',
                    ]);
                }

                $updates['adviser_id'] = $adviserId;
            }

            $document->registrationDetail()->update($updates);

            $this->engine->resubmit($document, $actor);
            $document->refresh();

            return $document;
        });
    }
}
