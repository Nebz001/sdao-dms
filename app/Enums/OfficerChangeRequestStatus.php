<?php

namespace App\Enums;

/**
 * Lifecycle of a current officer's request to change who holds a seat
 * (App\Organizations\RequestOfficerChange). Pending until an SDAO admin
 * decides it; Approved, Declined and Withdrawn are all terminal — none has a
 * revival, same "file a brand-new one" spirit as JoinRequestStatus.
 *
 * Withdrawn is the system closing a request on its own because the officer who
 * filed it no longer holds a seat in the organization (see
 * OrganizationMembershipService::withdrawOrphanedChangeRequests) — a pending
 * request never outlives its requester's authority. Nobody reviewed it, so it
 * is not Declined.
 */
enum OfficerChangeRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
}
