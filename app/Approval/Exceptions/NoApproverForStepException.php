<?php

namespace App\Approval\Exceptions;

use App\Enums\Role;
use RuntimeException;

/**
 * Thrown, before anything is saved, when the step a document is about to enter
 * has no active holder who could act on it — no adviser, program chair, dean,
 * principal or director in post, or fewer active SDAO members than the step
 * needs. Raised only by StepApproverGuard, so there is one check and one
 * wording for every form and every chain variant.
 *
 * The message is written for whoever triggered the action: the officer
 * submitting or resubmitting is told which role their organization lacks, an
 * approver is told which role is missing and that SDAO has to assign one.
 * It is rendered as a toast (web) or a 422 (api) by bootstrap/app.php.
 */
class NoApproverForStepException extends RuntimeException
{
    public function __construct(
        public readonly Role $role,
        string $message,
        private readonly string $title,
    ) {
        parent::__construct($message);
    }

    /** The officer submitting or resubmitting a document. */
    public static function forSubmitter(Role $role): self
    {
        $who = match ($role) {
            Role::Adviser => 'Your organization has no active adviser.',
            Role::ProgramChair => 'Your organization’s program has no active program chair.',
            Role::Dean => 'Your organization’s school has no active dean.',
            Role::Principal => 'Your organization’s school has no active principal.',
            Role::SdaoMember => 'SDAO has no active members to review this.',
            default => "There is no active {$role->stepLabel()} to review this.",
        };

        return new self($role, "{$who} Please contact SDAO.", 'Can’t submit yet');
    }

    /** The approver whose approval would move the document into the empty step. */
    public static function forApprover(Role $role): self
    {
        $label = $role === Role::SdaoMember ? 'SDAO member' : mb_strtolower($role->stepLabel());

        return new self(
            $role,
            "Approving would move this document to the {$label} step, but no active {$label} is assigned. SDAO needs to assign one before it can move on.",
            'Can’t approve yet',
        );
    }

    public function title(): string
    {
        return $this->title;
    }
}
