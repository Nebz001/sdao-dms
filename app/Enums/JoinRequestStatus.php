<?php

namespace App\Enums;

/**
 * Lifecycle of a student's request to join an existing organization
 * (App\Organizations\RequestToJoinOrganization). Pending until an adviser or
 * active officer of the target org decides it; Approved, Declined and
 * Withdrawn are all terminal — a declined student must file a brand-new
 * request, same "no revival of a terminal record" spirit as DocumentStatus.
 *
 * Withdrawn is the system closing a request on its own because the student
 * has since become an officer by another route (adviser bind, an approved
 * officer change, or founding an organization) — see
 * OrganizationMembershipService::withdrawPendingJoinRequestsFor. Nobody
 * reviewed it, so it is not Declined.
 */
enum JoinRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
}
