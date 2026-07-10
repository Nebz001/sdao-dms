<?php

namespace App\Enums;

/**
 * "Type of Activity" on the Activity Request Form (proposal step 1).
 */
enum ActivityType: string
{
    case SeminarWorkshop = 'seminar_workshop';
    case GeneralAssembly = 'general_assembly';
    case Orientation = 'orientation';
    case Competition = 'competition';
    case RecruitmentAudition = 'recruitment_audition';
    case DonationDriveFundraising = 'donation_drive_fundraising';
    case Outreach = 'outreach';
    case OffCampusActivity = 'off_campus_activity';
    case Others = 'others';

    public function label(): string
    {
        return match ($this) {
            self::SeminarWorkshop => 'Seminar/Workshop',
            self::GeneralAssembly => 'General Assembly',
            self::Orientation => 'Orientation',
            self::Competition => 'Competition',
            self::RecruitmentAudition => 'Recruitment/Audition',
            self::DonationDriveFundraising => 'Donation Drive/Fundraising Activity',
            self::Outreach => 'Outreach',
            self::OffCampusActivity => 'Off-campus Activity',
            self::Others => 'Others',
        };
    }
}
