import { Link } from '@inertiajs/react';
import { ArrowRight, BarChart3, CircleCheck, Clock, Inbox } from 'lucide-react';
import type { ReactNode } from 'react';
import { ToneBadge } from '@/components/status-badge';
import { cn } from '@/lib/utils';
import AgingBar from './aging-bar';
import SegmentedBar from './segmented-bar';
import Sparkline from './sparkline';
import StatCard, { StatCardSkeleton, StatValue } from './stat-card';
import { formatDate } from './types';
import type { QueueRow, QueueStats, ReviewQueueConfig, WaitTier } from './types';
import WaitPill from './wait-pill';
import { TIER_ACCENT_TEXT, TIER_CARD_TONE } from './wait-tier';

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

/**
 * The one "Oldest waiting" card. Its whole accent (border, tinted surface,
 * icon chip, heading and link) follows the waiting-time band of the item it
 * shows, read from wait-tier.ts, and the pill inside uses that same band, so
 * the card and its pill always agree. 0 to 2 days keeps the normal card
 * surface; 3 to 7 is amber; 8+ is red. The day count in the pill is the
 * non-color cue in every state.
 */
export function OldestWaitingCard({
    title = 'Oldest waiting',
    waiting,
    headline,
    headlineDetail,
    children,
    action,
    emptyText,
}: {
    title?: string;
    /** The oldest item's wait; null when nothing is waiting. */
    waiting: {
        tier: WaitTier;
        days: number;
        suffix?: string;
        flagOverdue?: boolean;
    } | null;
    headline: string;
    /** Quiet second line under the headline, e.g. a person's role. */
    headlineDetail?: string | null;
    /** The key and value rows under the headline (a `dl`). */
    children: ReactNode;
    /** Receives the band's link classes, so the link takes the card's accent. */
    action: (className: string) => ReactNode;
    emptyText: string;
}) {
    return (
        <StatCard icon={Clock} title={title} tone={waiting ? TIER_CARD_TONE[waiting.tier] : 'default'}>
            {waiting ? (
                <>
                    <div className="flex min-w-0 flex-col gap-1">
                        <p className="truncate text-xl leading-none font-semibold" title={headline}>
                            {headline}
                        </p>
                        {headlineDetail && (
                            <p className="text-xs leading-snug break-words text-muted-foreground">{headlineDetail}</p>
                        )}
                    </div>
                    {children}
                    <div className="mt-auto flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                        {/* Backed by the plain card surface so the pill tint does not stack on the card tint (that dropped light-theme contrast below 4.5:1). */}
                        <span className="rounded-full bg-card">
                            <WaitPill
                            days={waiting.days}
                            tier={waiting.tier}
                            suffix={waiting.suffix}
                            flagOverdue={waiting.flagOverdue}
                        />
                        </span>
                        {action(
                            cn('inline-flex items-center gap-1.5 text-sm font-medium hover:underline', TIER_ACCENT_TEXT[waiting.tier]),
                        )}
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
    const oldest = rows[0];
    const heading = byTitle ? (oldest?.title ?? '') : (oldest?.organization.name ?? '');

    return (
        <OldestWaitingCard
            title={title}
            headline={heading}
            emptyText={emptyText}
            waiting={
                oldest
                    ? {
                          tier: oldest.tier,
                          days: oldest.waiting_days,
                          suffix: byTitle ? undefined : 'waiting',
                          flagOverdue: byTitle,
                      }
                    : null
            }
            action={(className) =>
                oldest && (
                    <Link href={oldest.href ?? config.showRoute(oldest.id)} className={className}>
                        Review {oldest.noun ?? config.noun}
                        <ArrowRight className="size-3.5" aria-hidden />
                    </Link>
                )
            }
        >
            {oldest && (
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
            )}
        </OldestWaitingCard>
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
