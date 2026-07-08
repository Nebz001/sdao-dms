<?php

namespace App\Renewals;

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\OrganizationType;
use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class UpdateOrganizationRenewal
{
    public function __construct(private readonly ApprovalEngine $engine) {}

    /**
     * @param  array<int, string>|null  $roster
     *
     * @throws AuthorizationException
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
    ): Document {
        if ($document->status !== DocumentStatus::Returned) {
            throw new AuthorizationException('Only returned documents can be edited.');
        }

        if ($document->submitted_by !== $actor->id) {
            throw new AuthorizationException('Only the original submitter may edit this document.');
        }

        return DB::transaction(function () use (
            $actor, $document, $organizationType, $description,
            $contactPerson, $contactNumber, $contactEmail, $dateOrganized, $roster
        ) {
            // academic_year is intentionally NOT included: it is set once at
            // creation (SubmitOrganizationRenewal) and must never change across
            // the return/resubmit cycle.
            $document->registrationDetail()->update([
                'organization_type' => $organizationType->value,
                'description' => $description,
                'contact_person' => $contactPerson,
                'contact_number' => $contactNumber,
                'contact_email' => $contactEmail,
                'date_organized' => $dateOrganized,
                'roster' => $roster,
            ]);

            $this->engine->resubmit($document, $actor);
            $document->refresh();

            return $document;
        });
    }
}
