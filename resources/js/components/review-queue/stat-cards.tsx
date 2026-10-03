import { Link } from '@inertiajs/react';
import { ArrowRight, BarChart3, CircleCheck, Clock, Inbox } from 'lucide-react';
import { ToneBadge } from '@/components/status-badge';
import { cn } from '@/lib/utils';
import AgingBar from './aging-bar';
import SegmentedBar from './segmented-bar';
import Sparkline from './sparkline';
import StatCard, { StatCardSkeleton, StatValue } from './stat-card';
import { formatDate } from './types';
import type { QueueRow, QueueStats, ReviewQueueConfig } from './types';
import WaitPill from './wait-pill';

const DASH = '—';

export function WaitingCard({ rows }: { rows: QueueRow[] }) {
    const overdue = rows.filter((r) => r.tier === 'overdue').length;

    return (
        <StatCard icon={Inbox} title="Waiting for review">
            <div className="flex items-center justify-between gap-3">
                <StatValue>{rows.length}</StatValue>
                {overdue > 0 && (
                    <ToneBadge tone="destructive" className="text-xs tracking-normal tabular-nums normal-case">
                        {overdue} over 7 days
                    </ToneBadge>
                )}
            </div>
            <AgingBar rows={rows} />
        </StatCard>
    );
}

export function OldestCard({ rows, config }: { rows: QueueRow[]; config: ReviewQueueConfig }) {
    const oldest = rows[0];
    const overdue = oldest !== undefined && oldest.tier === 'overdue';

    return (
        <StatCard icon={Clock} title="Oldest waiting" tone={overdue ? 'alert' : 'default'}>
            {oldest ? (
                <>
                    <p className="truncate text-xl leading-none font-semibold" title={oldest.organization.name}>
                        {oldest.organization.name}
                    </p>
                    <dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-1.5 text-sm">
                        <dt className="text-muted-foreground">Type</dt>
                        <dd className="font-medium">{config.typeLabel}</dd>
                        <dt className="text-muted-foreground">Submitted</dt>
                        <dd className="font-medium tabular-nums">{formatDate(oldest.submitted_at)}</dd>
                    </dl>
                    <div className="mt-auto flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                        <WaitPill days={oldest.waiting_days} tier={oldest.tier} suffix="waiting" />
                        <Link
                            href={config.showRoute(oldest.id)}
                            className={cn(
                                'inline-flex items-center gap-1.5 text-sm font-medium hover:underline',
                                overdue ? 'text-destructive-foreground' : 'text-primary-text',
                            )}
                        >
                            Review {config.noun}
                            <ArrowRight className="size-3.5" aria-hidden />
                        </Link>
                    </div>
                </>
            ) : (
                <>
                    <p className="text-xl leading-none font-semibold text-muted-foreground">{DASH}</p>
                    <p className="text-sm text-muted-foreground">Nothing is waiting for review.</p>
                </>
            )}
        </StatCard>
    );
}

export function SubmittedCard({ stats }: { stats: QueueStats['submitted'] }) {
    return (
        <StatCard icon={BarChart3} title="Submitted this term">
            <StatValue>{stats.total}</StatValue>
            {stats.weeks.length > 0 ? (
                <Sparkline
                    values={stats.weeks}
                    label={`Submissions per week, last ${stats.weeks.length} weeks: ${stats.weeks.join(', ')}`}
                />
            ) : (
                <p className="flex h-10 items-end text-sm text-muted-foreground">{DASH}</p>
            )}
            <p className="text-sm text-muted-foreground">
                <strong className="font-semibold text-foreground tabular-nums">{stats.thisWeek}</strong> this week
            </p>
        </StatCard>
    );
}

export function DecidedCard({ stats }: { stats: QueueStats['decided'] }) {
    return (
        <StatCard icon={CircleCheck} title="Decided this term">
            <StatValue>{stats.total}</StatValue>
            <SegmentedBar
                ariaLabel={`Decided this term: ${stats.approved} approved, ${stats.returned} returned, ${stats.rejected} rejected`}
                segments={[
                    { label: 'Approved', count: stats.approved, className: 'bg-success' },
                    { label: 'Returned', count: stats.returned, className: 'bg-warning' },
                    // Rejection is terminal and rare; only listed when it happened.
                    ...(stats.rejected > 0
                        ? [{ label: 'Rejected', count: stats.rejected, className: 'bg-destructive' }]
                        : []),
                ]}
            />
        </StatCard>
    );
}

export function SubmittedCardSkeleton() {
    return <StatCardSkeleton icon={BarChart3} title="Submitted this term" />;
}

export function DecidedCardSkeleton() {
    return <StatCardSkeleton icon={CircleCheck} title="Decided this term" />;
}
