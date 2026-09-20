<?php

namespace App\Enums;

/**
 * Lifecycle of a current officer's request to change who holds a seat
 * (App\Organizations\RequestOfficerChange). Pending until an SDAO admin
 * decides it; both Approved and Declined are terminal — a declined request
 * has no revival, same "file a brand-new one" spirit as JoinRequestStatus.
 */
enum OfficerChangeRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
}
