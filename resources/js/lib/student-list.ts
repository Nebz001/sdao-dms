export type ListFormKind =
    | 'registration'
    | 'renewal'
    | 'calendar'
    | 'proposal'
    | 'report';

export type TabKey = 'all' | 'in_review' | 'approved' | 'returned' | 'draft';

export const TAB_LABELS: Record<TabKey, string> = {
    all: 'All',
    in_review: 'In review',
    approved: 'Approved',
    returned: 'Returned',
    draft: 'Drafts',
};

const APPROVED_NOTE: Record<ListFormKind, string> = {
    registration: 'Your organization is registered',
    renewal: 'Your organization is renewed',
    calendar: 'Activities can now be proposed',
    proposal: 'You can hold this activity',
    report: 'SDAO accepted this report',
};

/**
 * The plain line under a status badge. "Now with" and "Sent back by" name the
 * real role the document's chain is waiting on; when that is unknown the line
 * says the status without naming anyone (it never shows a placeholder).
 */
export function statusNote(
    status: string,
    kind: ListFormKind,
    currentApprover: string | null,
): string | null {
    switch (status) {
        case 'approved':
            return APPROVED_NOTE[kind];
        case 'in_review':
            return currentApprover ? `Now with: ${currentApprover}` : null;
        case 'returned':
            return currentApprover
                ? `Sent back by ${currentApprover}`
                : 'Sent back for changes';
        case 'rejected':
            return 'Stopped for good. File a new one if needed';
        case 'draft':
            return 'Not sent yet';
        default:
            return null;
    }
}

/** Counts per tab over the given statuses; `all` counts everything, drafts and rejections included. */
export function tabCounts(statuses: string[]): Record<TabKey, number> {
    const count = (status: string) =>
        statuses.filter((s) => s === status).length;

    return {
        all: statuses.length,
        in_review: count('in_review'),
        approved: count('approved'),
        returned: count('returned'),
        draft: count('draft'),
    };
}

export function matchesTab(status: string, tab: TabKey): boolean {
    return tab === 'all' || status === tab;
}

export function isTabKey(value: unknown): value is TabKey {
    return typeof value === 'string' && value in TAB_LABELS;
}

/** "Sep 5, 2026", in the viewer's own locale. */
export function formatListDate(value: string): string {
    return new Date(value).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}
