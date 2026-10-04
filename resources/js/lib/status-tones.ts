import { statusLabel } from '@/lib/utils';

/**
 * The ONE place that maps a status to a tone and a label. Every badge in the
 * app reads from this table, so a status can never be green on one page and
 * blue on another. The five tones are the five existing color tokens:
 * success, info, warning, destructive, and neutral (gray).
 *
 * When a PHP enum gains a case, add it here: a Pest test fails if any case of
 * DocumentStatus, TransitionAction, OrganizationStatus, AccountStatus,
 * OfficerChangeRequestStatus, JoinRequestStatus or RenewalEligibility is
 * missing from its domain below. Keep the one-entry-per-line format; that test
 * reads this file.
 */
export type Tone = 'success' | 'info' | 'warning' | 'destructive' | 'neutral';

export type StatusDomain =
    | 'document'
    | 'action'
    | 'organization'
    | 'account'
    | 'request'
    | 'renewal'
    | 'requirement'
    | 'wait'
    | 'idle'
    | 'venue'
    | 'flag';

type Entry = { tone: Tone; label: string };

const TABLE: Record<StatusDomain, Record<string, Entry>> = {
    // App\Enums\DocumentStatus
    document: {
        draft: { tone: 'neutral', label: 'Draft' },
        in_review: { tone: 'info', label: 'In review' },
        returned: { tone: 'warning', label: 'Returned' },
        approved: { tone: 'success', label: 'Approved' },
        rejected: { tone: 'destructive', label: 'Rejected' },
    },
    // App\Enums\TransitionAction
    action: {
        submitted: { tone: 'info', label: 'Submitted' },
        resubmitted: { tone: 'info', label: 'Resubmitted' },
        approved: { tone: 'success', label: 'Approved' },
        advanced: { tone: 'success', label: 'Advanced' },
        completed: { tone: 'success', label: 'Completed' },
        returned: { tone: 'warning', label: 'Returned' },
        rejected: { tone: 'destructive', label: 'Rejected' },
        withdrawn: { tone: 'neutral', label: 'Withdrawn' },
    },
    // App\Enums\OrganizationStatus
    organization: {
        active: { tone: 'success', label: 'Active' },
        pending_review: { tone: 'info', label: 'Pending review' },
        needs_renewal: { tone: 'warning', label: 'Needs renewal' },
        inactive: { tone: 'neutral', label: 'Inactive' },
    },
    // App\Enums\AccountStatus
    account: {
        verified: { tone: 'success', label: 'Verified' },
        unverified: { tone: 'warning', label: 'Pending verification' },
        rejected: { tone: 'destructive', label: 'Not approved' },
    },
    // App\Enums\OfficerChangeRequestStatus and App\Enums\JoinRequestStatus
    request: {
        pending: { tone: 'info', label: 'Pending' },
        approved: { tone: 'success', label: 'Approved' },
        declined: { tone: 'destructive', label: 'Declined' },
        withdrawn: { tone: 'neutral', label: 'Withdrawn' },
    },
    // App\Enums\RenewalEligibility, plus the organization "renewal due" flag
    renewal: {
        eligible: { tone: 'success', label: 'Renewal open' },
        already_filed: { tone: 'info', label: 'Already filed' },
        not_yet_due: { tone: 'neutral', label: 'Not yet due' },
        season_closed: { tone: 'neutral', label: 'Season closed' },
        no_prior_record: { tone: 'neutral', label: 'No prior record' },
        due: { tone: 'warning', label: 'Renewal due' },
    },
    // The requirements checklist states sent by StudentDashboardData
    requirement: {
        done: { tone: 'success', label: 'Done' },
        in_progress: { tone: 'info', label: 'In review' },
        action_needed: { tone: 'warning', label: 'Missing' },
        not_applicable: { tone: 'neutral', label: 'Not due' },
        info: { tone: 'neutral', label: 'Info' },
    },
    // ApproverQueue::waitTier()
    wait: {
        normal: { tone: 'neutral', label: 'On track' },
        warning: { tone: 'warning', label: 'Getting close' },
        overdue: { tone: 'destructive', label: 'Overdue' },
    },
    // InReviewSnapshot::tierFor()
    idle: {
        fresh: { tone: 'success', label: 'Recent' },
        aging: { tone: 'warning', label: 'Getting old' },
        stale: { tone: 'destructive', label: 'Overdue' },
    },
    // A venue booking on the calendar
    venue: {
        confirmed: { tone: 'success', label: 'Confirmed' },
        tentative: { tone: 'warning', label: 'Tentative' },
    },
    // Short flags on a document or account
    flag: {
        resubmitted: { tone: 'info', label: 'Resubmitted' },
        urgent: { tone: 'destructive', label: 'Urgent' },
        flagged: { tone: 'warning', label: 'Flagged for revision' },
        deactivated: { tone: 'destructive', label: 'Deactivated' },
    },
};

/**
 * An unrecognized value reads as a neutral badge with its own words, never a
 * guessed color.
 */
function entryFor(domain: StatusDomain, value: string): Entry {
    return TABLE[domain][value] ?? { tone: 'neutral', label: statusLabel(value) };
}

export function toneFor(domain: StatusDomain, value: string): Tone {
    return entryFor(domain, value).tone;
}

export function labelFor(domain: StatusDomain, value: string): string {
    return entryFor(domain, value).label;
}

/** Every value a domain knows, for tests and for filters that list them. */
export function valuesFor(domain: StatusDomain): string[] {
    return Object.keys(TABLE[domain]);
}
