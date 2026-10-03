export type WaitTier = 'fresh' | 'aging' | 'overdue';

export type QueueRow = {
    id: number;
    title: string;
    organization: { id: number; name: string };
    /** ISO timestamp the document entered the approver's queue. */
    submitted_at: string;
    waiting_days: number;
    /** Computed by ReviewQueueData::tierFor(); thresholds live only in PHP. */
    tier: WaitTier;
    /** The page-specific column value (college, period, activity). */
    extra: string | null;
    /** Multi-step chains only (activity proposals): where the document sits in its route. */
    step?: { position: number; total: number; name: string } | null;
};

export type QueueStats = {
    submitted: { total: number; thisWeek: number; weeks: number[] };
    decided: { approved: number; returned: number; rejected: number; total: number };
};

export type RecentDecision = {
    id: number;
    organization: string;
    /** Set where the document has its own name (activity proposals). */
    title?: string;
    result: 'approved' | 'returned' | 'rejected';
    decided_at: string;
    decided_by: string | null;
    href: string;
};

export type ShowRoute = (id: number) => string;

/** Everything that differs between the four review queue pages. */
export type ReviewQueueConfig = {
    headTitle: string;
    title: string;
    subtitle: string;
    /** Singular, lower case: "registration". */
    noun: string;
    /** Capitalised document kind shown on the oldest card: "Registration". */
    typeLabel: string;
    emptyDescription: string;
    showRoute: ShowRoute;
};

export function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString();
}

export function pluralDays(days: number): string {
    return days === 0 ? 'Today' : days === 1 ? '1 day' : `${days} days`;
}
