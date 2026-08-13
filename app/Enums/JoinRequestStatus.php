<?php

namespace App\Enums;

/**
 * Lifecycle of a student's request to join an existing organization
 * (App\Organizations\RequestToJoinOrganization). Pending until an adviser or
 * active officer of the target org decides it; both Approved and Declined
 * are terminal — a declined student must file a brand-new request, same
 * "no revival of a terminal record" spirit as DocumentStatus.
 */
enum JoinRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
}
