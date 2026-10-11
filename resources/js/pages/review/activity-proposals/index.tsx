import { Deferred, Head } from '@inertiajs/react';
import PageHeader from '@/components/page-header';
import QueueFilterTabs from '@/components/review-queue/filter-tabs';
import type { QueueFilter } from '@/components/review-queue/filter-tabs';
import {
    PendingTable,
    RecentDecisionsSkeleton,
    RecentDecisionsTable,
} from '@/components/review-queue/queue-tables';
import {
    DecidedCard,
    DecidedCardSkeleton,
    OldestCard,
    SubmittedCard,
    SubmittedCardSkeleton,
    WaitingCard,
} from '@/components/review-queue/stat-cards';
import type {
    QueueRow,
    QueueStats,
    RecentDecision,
    ReviewQueueConfig,
} from '@/components/review-queue/types';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import * as reviewActivityProposals from '@/routes/review/activity-proposals';

type Filter = QueueFilter;

/**
 * Live tabs carry full queue rows with `decision: null`; history tabs carry
 * only id, title, organization and `decision`.
 */
type TabRow = Pick<QueueRow, 'id' | 'title' | 'organization'> &
    Partial<QueueRow> & {
        college?: string | null;
        decision: {
            action: RecentDecision['result'];
            decided_at: string;
        } | null;
    };

type Props = {
    queue: TabRow[];
    filter: Filter;
    filterLabel: string | null;
    academicYear: string;
    /** Every proposal waiting on this approver, whichever tab is open. */
    pending: QueueRow[];
    tabCounts: {
        pending: number;
        overdue: number;
        approved: number;
        returned: number;
    };
    /** True for SDAO only: they alone get the term-wide submitted and decided cards. */
    showTermStats: boolean;
    /** Deferred, SDAO only: undefined until the second request lands. */
    stats?: QueueStats;
    recent?: RecentDecision[];
};

function tabHref(value: Filter): string {
    return value === null
        ? reviewActivityProposals.index().url
        : reviewActivityProposals.index({ query: { filter: value } }).url;
}

function showRoute(id: number): string {
    return reviewActivityProposals.show({ document: id }).url;
}

const config: ReviewQueueConfig = {
    headTitle: 'Review Activity Proposals',
    title: 'Activity Proposals Review Queue',
    subtitle: 'Activity proposals routed to your step',
    noun: 'proposal',
    typeLabel: 'Activity proposal',
    emptyDescription:
        'Proposals will show up here once they reach a step routed to your role.',
    showRoute,
};

export default function ReviewActivityProposalsIndex({
    queue,
    filter,
    filterLabel,
    academicYear,
    pending,
    tabCounts,
    showTermStats,
    stats,
    recent,
}: Props) {
    useDocumentUpdates(['queue', 'pending', 'tabCounts', ...(showTermStats ? ['stats'] : []), 'recent']);

    const isHistory = filter !== null && filter !== 'overdue';

    const decisions: RecentDecision[] = queue.map((row) => ({
        id: row.id,
        title: row.title,
        organization: row.organization.name,
        college: row.college ?? null,
        result: row.decision?.action ?? 'approved',
        decided_at: row.decision?.decided_at ?? '',
        decided_by: null,
        href: showRoute(row.id),
    }));

    return (
        <>
            <Head title={config.headTitle} />

            <div className="flex flex-col gap-6">
                <PageHeader
                    title={config.title}
                    subtitle="Activity proposals routed to your step"
                />

                <div
                    className={
                        showTermStats
                            ? 'grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1fr)]'
                            : 'grid grid-cols-1 gap-4 md:grid-cols-2'
                    }
                >
                    <WaitingCard
                        rows={pending}
                        title="Waiting on you"
                        overdueLabel={(n) => `${n} overdue`}
                        pillInHeader
                    />
                    <OldestCard
                        rows={pending}
                        config={config}
                        headline="title"
                    />
                    {showTermStats && (
                        <Deferred
                            data="stats"
                            fallback={
                                <>
                                    <SubmittedCardSkeleton />
                                    <DecidedCardSkeleton />
                                </>
                            }
                        >
                            {stats && (
                                <>
                                    <SubmittedCard stats={stats.submitted} />
                                    <DecidedCard stats={stats.decided} />
                                </>
                            )}
                        </Deferred>
                    )}
                </div>

                <QueueFilterTabs
                    label="Filter proposals"
                    filter={filter}
                    counts={tabCounts}
                    hrefFor={tabHref}
                />
                {isHistory ? (
                    <RecentDecisionsTable
                        rows={decisions}
                        title={filterLabel ?? 'Decisions'}
                        aside={academicYear}
                        emptyText="You haven't made any matching decisions this academic year."
                        showActivity
                        showDecidedBy={false}
                        showCollege
                    />
                ) : (
                    <>
                        <PendingTable
                            rows={queue as QueueRow[]}
                            config={{
                                ...config,
                                emptyDescription:
                                    filter === 'overdue'
                                        ? 'No proposal has waited 8 or more days on you.'
                                        : config.emptyDescription,
                            }}
                            title={
                                filter === 'overdue'
                                    ? 'Overdue'
                                    : 'Pending your action'
                            }
                            emptyTitle={
                                filter === 'overdue'
                                    ? 'Nothing overdue'
                                    : 'Nothing waiting on you'
                            }
                            showActivity
                            showStep
                            flagOverdue
                            showCollege
                        />

                        <Deferred
                            data="recent"
                            fallback={<RecentDecisionsSkeleton />}
                        >
                            <RecentDecisionsTable
                                rows={recent ?? []}
                                showActivity
                                showDecidedBy={false}
                                showCollege
                            />
                        </Deferred>
                    </>
                )}
            </div>
        </>
    );
}

ReviewActivityProposalsIndex.layout = {
    breadcrumbs: [{ title: 'Review Activity Proposals' }],
};
