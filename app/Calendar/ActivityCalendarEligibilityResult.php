<?php

namespace App\Calendar;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Support\AcademicPeriod;

/**
 * The result of SubmitActivityCalendar::eligibilityFor() — governs Activity
 * Calendar submission only. Never consult this to gate an Activity Proposal;
 * see the scope warning on eligibilityFor() itself.
 */
final readonly class ActivityCalendarEligibilityResult
{
    public function __construct(
        public AcademicPeriod $currentPeriod,
        public ?Document $existingDocument,
    ) {}

    public function isEligible(): bool
    {
        return $this->existingDocument === null;
    }

    public function message(): ?string
    {
        if ($this->existingDocument === null) {
            return null;
        }

        return match ($this->existingDocument->status) {
            DocumentStatus::Returned => "Your organization's activity calendar for {$this->currentPeriod->label()} was returned for revision. Edit and resubmit it instead of starting a new one.",
            DocumentStatus::Approved => "Your organization's activity calendar for {$this->currentPeriod->label()} has already been approved. You can submit a new one once the period advances to the next term.",
            default => "Your organization already has an activity calendar for {$this->currentPeriod->label()} pending SDAO review.",
        };
    }
}
