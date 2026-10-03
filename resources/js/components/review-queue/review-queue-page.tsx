import { Deferred, Head } from '@inertiajs/react';
import PageHeader from '@/components/page-header';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import { PendingTable, RecentDecisionsSkeleton, RecentDecisionsTable } from './queue-tables';
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
    queue: QueueRow[];
    extraColumnLabel: string;
    /** Deferred: undefined until the second request lands. */
    stats?: QueueStats;
    recent?: RecentDecision[];
};

/**
 * The one layout behind all four SDAO review queues (registrations, renewals,
 * calendars, reports). Each page passes its own wording and routes through
 * `config`; the data comes from App\Approval\ReviewQueueData.
 */
export default function ReviewQueuePage({
    config,
    queue,
    extraColumnLabel,
    stats,
    recent,
}: ReviewQueuePageProps & { config: ReviewQueueConfig }) {
    useDocumentUpdates(['queue', 'stats', 'recent']);

    return (
        <>
            <Head title={config.headTitle} />

            <div className="flex flex-col gap-6">
                <PageHeader title={config.title} subtitle={config.subtitle} />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-[1.3fr_1.3fr_1fr_1fr]">
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

                <PendingTable rows={queue} extraColumnLabel={extraColumnLabel} config={config} />

                <Deferred data="recent" fallback={<RecentDecisionsSkeleton />}>
                    <RecentDecisionsTable rows={recent ?? []} />
                </Deferred>
            </div>
        </>
    );
}
