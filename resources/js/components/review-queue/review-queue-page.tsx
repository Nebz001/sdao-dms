import { Deferred, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import PageHeader from '@/components/page-header';
import PaginationFooter from '@/components/pagination-footer';
import type {
    PaginationLinks,
    PaginationMeta,
} from '@/components/pagination-footer';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import { cn } from '@/lib/utils';
import QueueFilterTabs from './filter-tabs';
import type { QueueFilter, QueueTabCounts } from './filter-tabs';
import { PendingTable, RecentDecisionsTable } from './queue-tables';
import {
    DecidedCard,
    DecidedCardSkeleton,
    OldestCard,
    SubmittedCard,
    SubmittedCardSkeleton,
    WaitingCard,
} from './stat-cards';
import type { QueueRow, QueueStats, RecentDecision, ReviewQueueConfig } from './types';

export type ReviewQueuePageProps = {
    /** Every pending row, whichever tab is open (the stat cards read it). */
    queue: QueueRow[];
    extraColumnLabel: string;
    filter: QueueFilter;
    filterLabel: string | null;
    academicYear: string;
    tabCounts: QueueTabCounts;
    /** One page of the selected tab: queue rows, or decisions on a history tab. */
    rows: QueueRow[] | RecentDecision[];
    pagination: { meta: PaginationMeta; links: PaginationLinks };
    /** Deferred: undefined until the second request lands. */
    stats?: QueueStats;
};

/**
 * The one layout behind the four SDAO review queues (registrations, renewals,
 * calendars, reports): stat cards, the shared status tabs, and one table card
 * for the selected tab. Each page passes its own wording and routes through
 * `config`; the data comes from App\Approval\ReviewQueueData::tabbedProps().
 */
export default function ReviewQueuePage({
    config,
    queue,
    extraColumnLabel,
    filter,
    filterLabel,
    academicYear,
    tabCounts,
    rows,
    pagination,
    stats,
}: ReviewQueuePageProps & { config: ReviewQueueConfig }) {
    useDocumentUpdates(['queue', 'tabCounts', 'rows', 'pagination', 'stats']);

    const [loading, setLoading] = useState(false);
    const isHistory = filter !== null && filter !== 'overdue';
    const indexUrl = config.indexUrl ?? (() => '');

    function goToPage(url: string) {
        setLoading(true);
        router.get(
            url,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setLoading(false),
            },
        );
    }

    const footer = (
        <PaginationFooter
            meta={pagination.meta}
            links={pagination.links}
            onNavigate={goToPage}
        />
    );

    return (
        <>
            <Head title={config.headTitle} />

            <div className="flex flex-col gap-6">
                <PageHeader title={config.title} subtitle={config.subtitle} />

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1fr)]">
                    <WaitingCard rows={queue} />
                    <OldestCard rows={queue} config={config} />
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
                </div>

                <QueueFilterTabs
                    label={`Filter ${config.noun}s`}
                    filter={filter}
                    counts={tabCounts}
                    hrefFor={indexUrl}
                />

                <div
                    aria-busy={loading}
                    className={cn(loading && 'opacity-60 transition-opacity')}
                >
                    {isHistory ? (
                        <RecentDecisionsTable
                            rows={rows as RecentDecision[]}
                            title={filterLabel ?? 'Decisions'}
                            aside={academicYear}
                            emptyText={`No matching ${config.noun} decisions this academic year.`}
                            showCollege
                            count={pagination.meta.total}
                            footer={footer}
                        />
                    ) : (
                        <PendingTable
                            rows={rows as QueueRow[]}
                            extraColumnLabel={extraColumnLabel}
                            config={{
                                ...config,
                                emptyDescription:
                                    filter === 'overdue'
                                        ? `No ${config.noun} has waited 8 or more days.`
                                        : config.emptyDescription,
                            }}
                            title={filter === 'overdue' ? 'Overdue' : 'Pending your action'}
                            emptyTitle={filter === 'overdue' ? 'Nothing overdue' : 'Nothing to review'}
                            flagOverdue
                            showCollege
                            count={pagination.meta.total}
                            footer={footer}
                        />
                    )}
                </div>
            </div>
        </>
    );
}
