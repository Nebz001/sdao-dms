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

export function WaitingCard({
    rows,
    title = 'Waiting for review',
    overdueLabel = (n) => `${n} over 7 days`,
    bucketLabels,
    pillInHeader = false,
}: {
    rows: QueueRow[];
    title?: string;
    /** Puts the overdue pill in the card header instead of beside the count. */
    pillInHeader?: boolean;
    /** Text of the red pill; queues with their own SLA word it differently. */
    overdueLabel?: (overdue: number) => string;
    bucketLabels?: [string, string, string];
}) {
    const overdue = rows.filter((r) => r.tier === 'overdue').length;

    const pill =
        overdue > 0 ? (
            <ToneBadge tone="destructive" className="text-xs tracking-normal tabular-nums normal-case">
                {overdueLabel(overdue)}
            </ToneBadge>
        ) : null;

    return (
        <StatCard icon={Inbox} title={title} headerAside={pillInHeader ? pill : undefined}>
            <div className="flex items-center justify-between gap-3">
                <StatValue>{rows.length}</StatValue>
                {!pillInHeader && pill}
            </div>
            <AgingBar rows={rows} labels={bucketLabels} />
        </StatCard>
    );
}

export function OldestCard({
    rows,
    config,
    headline = 'organization',
    title = 'Oldest waiting',
    detail = 'type',
    emptyText = 'Nothing is waiting for review.',
}: {
    rows: QueueRow[];
    config: ReviewQueueConfig;
    title?: string;
    /** "college" swaps the Type row for the row's `extra` value, labelled College. */
    detail?: 'type' | 'college';
    emptyText?: string;
    /** "title" leads with the document title and details the organization and route step (activity proposals). */
    headline?: 'organization' | 'title';
}) {
    const byTitle = headline === 'title';
    const heading = byTitle ? (rows[0]?.title ?? '') : (rows[0]?.organization.name ?? '');
    const oldest = rows[0];
    const overdue = oldest !== undefined && oldest.tier === 'overdue';

    return (
        <StatCard icon={Clock} title={title} tone={overdue ? 'alert' : 'default'}>
            {oldest ? (
                <>
                    <p className="truncate text-xl leading-none font-semibold" title={heading}>
                        {heading}
                    </p>
                    <dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-1.5 text-sm">
                        {byTitle ? (
                            <>
                                <dt className="text-muted-foreground">Organization</dt>
                                <dd className="truncate font-medium" title={oldest.organization.name}>
                                    {oldest.organization.name}
                                </dd>
                            </>
                        ) : detail === 'college' ? (
                            <>
                                <dt className="text-muted-foreground">College</dt>
                                <dd className="font-medium whitespace-normal">{oldest.extra ?? 'None'}</dd>
                            </>
                        ) : (
                            <>
                                <dt className="text-muted-foreground">Type</dt>
                                <dd className="font-medium">{config.typeLabel}</dd>
                            </>
                        )}
                        <dt className="text-muted-foreground">Submitted</dt>
                        <dd className="font-medium tabular-nums">{formatDate(oldest.submitted_at)}</dd>
                        {oldest.step && (
                            <>
                                <dt className="text-muted-foreground">Step</dt>
                                <dd className="font-medium">
                                    {oldest.step.position} of {oldest.step.total}, {oldest.step.name}
                                </dd>
                            </>
                        )}
                    </dl>
                    <div className="mt-auto flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                        <WaitPill
                            days={oldest.waiting_days}
                            tier={oldest.tier}
                            suffix={byTitle ? undefined : 'waiting'}
                            flagOverdue={byTitle}
                        />
                        <Link
                            href={oldest.href ?? config.showRoute(oldest.id)}
                            className={cn(
                                'inline-flex items-center gap-1.5 text-sm font-medium hover:underline',
                                overdue ? 'text-destructive-foreground' : 'text-primary-text',
                            )}
                        >
                            Review {oldest.noun ?? config.noun}
                            <ArrowRight className="size-3.5" aria-hidden />
                        </Link>
                    </div>
                </>
            ) : (
                <>
                    <p className="text-xl leading-none font-semibold text-muted-foreground">{DASH}</p>
                    <p className="text-sm text-muted-foreground">{emptyText}</p>
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
    // Only the outcomes that actually happened get a segment and a legend row.
    const segments = [
        { label: 'Approved', count: stats.approved, className: 'bg-success' },
        { label: 'Returned', count: stats.returned, className: 'bg-warning' },
        { label: 'Rejected', count: stats.rejected, className: 'bg-destructive' },
    ].filter((s) => s.count > 0);

    return (
        <StatCard icon={CircleCheck} title="Decided this term">
            <StatValue>{stats.total}</StatValue>
            {segments.length > 0 ? (
                <SegmentedBar
                    ariaLabel={`Decided this term: ${segments.map((s) => `${s.count} ${s.label.toLowerCase()}`).join(', ')}`}
                    segments={segments}
                />
            ) : (
                <p className="flex h-10 items-end text-sm text-muted-foreground">No decisions yet this term.</p>
            )}
        </StatCard>
    );
}

export function SubmittedCardSkeleton() {
    return <StatCardSkeleton icon={BarChart3} title="Submitted this term" />;
}

export function DecidedCardSkeleton() {
    return <StatCardSkeleton icon={CircleCheck} title="Decided this term" />;
}
