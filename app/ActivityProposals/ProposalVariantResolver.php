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
        $shs = $org->belongsToSeniorHighSchool();
        $on = $mode === ProposalCalendarMode::OnCalendar;

        return match (true) {
            ! $shs && $on => ProposalVariant::RegularOnCalendar,
            ! $shs && ! $on => ProposalVariant::RegularOffCalendar,
            $shs && $on => ProposalVariant::ShsOnCalendar,
            $shs && ! $on => ProposalVariant::ShsOffCalendar,
        };
    }
}
