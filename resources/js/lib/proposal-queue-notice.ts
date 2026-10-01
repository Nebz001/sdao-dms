export type ProposalQueueFilter =
    | 'overdue'
    | 'approved'
    | 'returned'
    | 'decided'
    | null;

export type ProposalQueueNotice = {
    tone: 'info' | 'warning';
    text: string;
};

const HISTORY_NOUN: Record<'approved' | 'returned' | 'decided', string> = {
    approved: 'approved proposals',
    returned: 'returned proposals',
    decided: 'decisions',
};

/**
 * The message under the proposal review header for the active tab, or null
 * when the list is empty (the list shows its own empty state). `count` is the
 * number of rows the tab's list renders, so the notice and the list always
 * agree.
 */
export function proposalQueueNotice(
    filter: ProposalQueueFilter,
    academicYear: string,
    count: number,
): ProposalQueueNotice | null {
    if (count === 0) {
        return null;
    }

    if (filter === null) {
        return {
            tone: 'info',
            text: `${count} proposal${count === 1 ? '' : 's'} awaiting your review.`,
        };
    }

    if (filter === 'overdue') {
        return {
            tone: 'warning',
            text: `${count} overdue proposal${count === 1 ? '' : 's'}.`,
        };
    }

    return {
        tone: 'info',
        text: `Showing ${HISTORY_NOUN[filter]} for ${academicYear} (${count}).`,
    };
}
