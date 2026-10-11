import { Link } from '@inertiajs/react';
import CountBadge from '@/components/count-badge';
import { Button } from '@/components/ui/button';

export type QueueFilter = 'overdue' | 'approved' | 'returned' | 'decided' | null;

export type QueueTabCounts = {
    pending: number;
    overdue: number;
    approved: number;
    returned: number;
};

const FILTER_TABS: Array<{
    value: QueueFilter;
    label: string;
    count?: keyof QueueTabCounts;
}> = [
    { value: null, label: 'Pending', count: 'pending' },
    { value: 'overdue', label: 'Overdue', count: 'overdue' },
    { value: 'approved', label: 'Approved', count: 'approved' },
    { value: 'returned', label: 'Returned', count: 'returned' },
    { value: 'decided', label: 'All decisions' },
];

/**
 * The status tabs shared by all five review queues: Pending, Overdue,
 * Approved, Returned, All decisions, each (bar the last) with its count. A
 * tab is a link to the same page with `?filter=`, so the server decides what
 * the table below shows. Wraps on narrow screens rather than scrolling.
 */
export default function QueueFilterTabs({
    label,
    filter,
    counts,
    hrefFor,
}: {
    /** Accessible name, e.g. "Filter proposals". */
    label: string;
    filter: QueueFilter;
    counts: QueueTabCounts;
    hrefFor: (value: QueueFilter) => string;
}) {
    return (
        <nav aria-label={label} className="flex flex-wrap gap-1.5">
            {FILTER_TABS.map((tab) => {
                const active = tab.value === filter;

                return (
                    <Button
                        key={tab.label}
                        asChild
                        size="sm"
                        variant={active ? 'secondary' : 'ghost'}
                    >
                        <Link
                            href={hrefFor(tab.value)}
                            aria-current={active ? 'page' : undefined}
                        >
                            {tab.label}
                            {tab.count && (
                                <CountBadge
                                    count={counts[tab.count]}
                                    variant="outline"
                                />
                            )}
                        </Link>
                    </Button>
                );
            })}
        </nav>
    );
}
