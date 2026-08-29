<?php

namespace App\ActivityProposals;

use App\Enums\ProposalCalendarMode;
use App\Enums\ProposalVariant;
use App\Models\Organization;

/**
 * Maps (org school structure) × (on/off calendar) → ProposalVariant.
 * This is the only place template-variant selection logic lives for proposals.
 */
class ProposalVariantResolver
{
    public function resolve(Organization $org, ProposalCalendarMode $mode): ProposalVariant
    {
        $on = $mode === ProposalCalendarMode::OnCalendar;

        // Checked before belongsToSeniorHighSchool(): a college-less org has
        // no school row at all, so it is neither "regular" nor "SHS" — its
        // own pair of variants skip program chair AND dean outright (Phase 2
        // remediation item 3), rather than substituting SHS's principal.
        if ($org->hasNoSchool()) {
            return $on ? ProposalVariant::ExtraCurricularOnCalendar : ProposalVariant::ExtraCurricularOffCalendar;
        }

        $shs = $org->belongsToSeniorHighSchool();

        return match (true) {
            ! $shs && $on => ProposalVariant::RegularOnCalendar,
            ! $shs && ! $on => ProposalVariant::RegularOffCalendar,
            $shs && $on => ProposalVariant::ShsOnCalendar,
            $shs && ! $on => ProposalVariant::ShsOffCalendar,
        };
    }
}
