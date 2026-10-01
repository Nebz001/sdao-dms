import { Head, Link } from '@inertiajs/react';
import { Inbox } from 'lucide-react';
import QueueStatStrip from '@/components/queue-stat-strip';
import { ActionBadge, StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import * as reviewActivityProposals from '@/routes/review/activity-proposals';

type QueueItem = {
    id: number;
    title: string;
    status: string;
    current_step_position: number | null;
    calendar_mode: string | null;
    organization: { id: number; name: string };
    created_at: string;
    waiting_since: string | null;
    wait_tier: 'normal' | 'warning' | 'overdue' | null;
    decision: { action: string; decided_at: string } | null;
};

type Filter = 'overdue' | 'approved' | 'returned' | 'decided' | null;

type Props = {
    queue: QueueItem[];
    filter: Filter;
    filterLabel: string | null;
    academicYear: string;
};

function modeLabel(mode: string | null): string {
    if (mode === 'on_calendar') {
        return 'On Calendar';
    }

    if (mode === 'off_calendar') {
        return 'Off Calendar';
    }

    return '';
}

const FILTER_TABS: Array<{ value: Filter; label: string }> = [
    { value: null, label: 'Pending' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'approved', label: 'Approved' },
    { value: 'returned', label: 'Returned' },
    { value: 'decided', label: 'All decisions' },
];

function tabHref(value: Filter): string {
    return value === null
        ? reviewActivityProposals.index().url
        : reviewActivityProposals.index({ query: { filter: value } }).url;
}

function headingCopy(
    filter: Filter,
    academicYear: string,
    count: number,
): string {
    if (filter === 'overdue') {
        return count === 0
            ? 'No overdue proposals'
            : `${count} overdue proposal${count === 1 ? '' : 's'}`;
    }

    if (
        filter === 'approved' ||
        filter === 'returned' ||
        filter === 'decided'
    ) {
        const verb =
            filter === 'decided'
                ? 'Decisions'
                : FILTER_TABS.find((t) => t.value === filter)?.label;

        return `${verb} · ${academicYear}${count > 0 ? ` (${count})` : ''}`;
    }

    return count === 0
        ? 'No proposals awaiting your review.'
        : `${count} proposal${count !== 1 ? 's' : ''} awaiting your review.`;
}

export default function ReviewActivityProposalsIndex({
    queue,
    filter,
    filterLabel,
    academicYear,
}: Props) {
    useDocumentUpdates(['queue']);

    const isHistory = filter !== null && filter !== 'overdue';

    const oldest =
        !isHistory && queue.length > 0
            ? new Date(
                  Math.min(
                      ...queue.map((d) => new Date(d.created_at).getTime()),
                  ),
              ).toLocaleDateString()
            : '—';

    return (
        <>
            <Head title="Review Activity Proposals" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-balance">
                        Activity Proposals — Review Queue
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {headingCopy(filter, academicYear, queue.length)}
                    </p>
                </div>

                <div
                    className="flex flex-wrap gap-1.5"
                    role="tablist"
                    aria-label="Filter proposals"
                >
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
                                    href={tabHref(tab.value)}
                                    aria-current={active ? 'page' : undefined}
                                >
                                    {tab.label}
                                </Link>
                            </Button>
                        );
                    })}
                </div>

                <QueueStatStrip
                    stats={
                        isHistory
                            ? [
                                  {
                                      label: 'Decisions',
                                      value: String(queue.length),
                                      count: queue.length,
                                  },
                              ]
                            : [
                                  {
                                      label: 'Pending',
                                      value: String(queue.length),
                                      count: queue.length,
                                  },
                                  { label: 'Oldest waiting', value: oldest },
                              ]
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {isHistory
                                ? (filterLabel ?? 'Decisions')
                                : 'Pending Your Action'}
                        </CardTitle>
                    </CardHeader>
                    <CardContent
                        className={queue.length > 0 ? 'divide-y' : undefined}
                    >
                        {queue.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <Inbox />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        {isHistory
                                            ? 'No decisions to show'
                                            : 'Nothing waiting on you'}
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        {isHistory
                                            ? `You haven't made any matching decisions this academic year.`
                                            : 'Proposals will show up here once they reach a step routed to your role.'}
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            queue.map((item) => (
                                <div
                                    key={item.id}
                                    className="flex items-center justify-between gap-4 py-3"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">
                                            {item.title}
                                        </p>
                                        <p className="truncate text-sm text-muted-foreground">
                                            {item.organization.name}
                                            {item.calendar_mode &&
                                                ` · ${modeLabel(item.calendar_mode)}`}
                                            {item.current_step_position !=
                                                null &&
                                                ` · Step ${item.current_step_position}`}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        {item.decision ? (
                                            <>
                                                <ActionBadge
                                                    action={
                                                        item.decision.action
                                                    }
                                                />
                                                <StatusBadge
                                                    status={item.status}
                                                />
                                            </>
                                        ) : (
                                            <StatusBadge status="in_review" />
                                        )}
                                        <Button
                                            asChild
                                            size="sm"
                                            variant={
                                                item.decision
                                                    ? 'outline'
                                                    : 'default'
                                            }
                                        >
                                            <Link
                                                href={
                                                    reviewActivityProposals.show(
                                                        { document: item.id },
                                                    ).url
                                                }
                                            >
                                                {item.decision
                                                    ? 'View'
                                                    : 'Review'}
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ReviewActivityProposalsIndex.layout = {
    breadcrumbs: [{ title: 'Review Activity Proposals' }],
};
