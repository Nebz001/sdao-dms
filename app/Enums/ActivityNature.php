<?php

namespace App\Enums;

/**
 * "Nature of Activity" on the Activity Request Form (proposal step 1).
 */
enum ActivityNature: string
{
    case CoCurricular = 'co_curricular';
    case NonCurricular = 'non_curricular';
    case CommunityExtension = 'community_extension';
    case Others = 'others';

    public function label(): string
    {
        return match ($this) {
            self::CoCurricular => 'Co-Curricular',
            self::NonCurricular => 'Non-curricular',
            self::CommunityExtension => 'Community Extension',
            self::Others => 'Others',
        };
    }
}
