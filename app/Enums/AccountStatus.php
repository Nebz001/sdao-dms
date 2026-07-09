<?php

namespace App\Enums;

/**
 * SDAO's manual account-verification gate for self-registered students,
 * distinct from Fortify's email-address verification. Unverified accounts can
 * log in but cannot submit anything or be adviser-bound as an officer.
 * Rejected is a permanent, distinct terminal state — the account is never
 * deleted, but never gains access either.
 */
enum AccountStatus: string
{
    case Unverified = 'unverified';
    case Verified = 'verified';
    case Rejected = 'rejected';
}
