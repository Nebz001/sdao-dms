import { Link } from '@inertiajs/react';
import { ArrowRight, Clock, Hourglass, TriangleAlert, UserRound } from 'lucide-react';
import SegmentedBar from '@/components/review-queue/segmented-bar';
import StatCard, { StatValue } from '@/components/review-queue/stat-card';
import { ToneBadge } from '@/components/status-badge';
import type { Tone } from '@/lib/status-tones';
import { cn } from '@/lib/utils';

export type StuckDocumentsStatsData = {
    total: number;
    withApprovers: number;
    returned: number;
    overThirty: number;
    idleLongest: {
        organization: string;
        formType: string;
        waitingOn: string;
        since: string;
        sinceDate: string;
        idleDays: number;
        tone: Tone;
        href: string;
    } | null;
    buckets: { under_7: number; '7_14': number; '15_30': number; over_30: number };
    medianDays: number;
    holdingMost: { name: string; role: string; stuck: number; longestDays: number } | null;
};

export function pluralDays(days: number): string {
    return `${days} ${days === 1 ? 'day' : 'days'}`;
}

const CARD_TONE = { neutral: 'default', warning: 'warning', destructive: 'alert' } as const;

const LINK_TEXT: Record<string, string> = {
    neutral: 'text-primary-text',
    warning: 'text-warning-foreground',
    destructive: 'text-destructive-foreground',
};

/** Pill backed by the plain card surface, so a tint never stacks on the card tint (keeps contrast at 4.5:1). */
function CardBackedBadge({ tone, children }: { tone: Tone; children: React.ReactNode }) {
    return (
        <span className="rounded-full bg-card">
            <ToneBadge tone={tone} className="text-xs tracking-normal normal-case tabular-nums">
                {children}
            </ToneBadge>
        </span>
    );
}

function StuckRightNowCard({ stats }: { stats: StuckDocumentsStatsData }) {
    return (
        <StatCard icon={Hourglass} title="Stuck right now">
            <div className="flex items-center justify-between gap-3">
                <StatValue>{stats.total}</StatValue>
                {stats.overThirty > 0 && (
                    <ToneBadge tone="destructive" className="text-xs tracking-normal normal-case tabular-nums">
                        {stats.overThirty} over 30 days
                    </ToneBadge>
                )}
            </div>
            <SegmentedBar
                ariaLabel={`Stuck right now: ${stats.withApprovers} with approvers, ${stats.returned} returned to organizations`}
                segments={[
                    { label: 'With approvers', count: stats.withApprovers, className: 'bg-info' },
                    { label: 'Returned to orgs', count: stats.returned, className: 'bg-warning' },
                ]}
            />
        </StatCard>
    );
}

function IdleLongestCard({ data }: { data: StuckDocumentsStatsData['idleLongest'] }) {
    return (
        <StatCard
            icon={TriangleAlert}
            title="Idle the longest"
            tone={data ? CARD_TONE[data.tone as keyof typeof CARD_TONE] : 'default'}
        >
            {data ? (
                <>
                    <p className="text-2xl leading-tight font-semibold break-words">{data.organization}</p>
                    <dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-1.5 text-sm">
                        <dt className="text-muted-foreground">Document</dt>
                        <dd className="font-medium">{data.formType}</dd>
                        <dt className="text-muted-foreground">Waiting on</dt>
                        <dd className="font-medium">{data.waitingOn}</dd>
                        <dt className="text-muted-foreground">Since</dt>
                        <dd className="font-medium tabular-nums">{data.sinceDate}</dd>
                    </dl>
                    <div className="mt-auto flex flex-wrap items-center justify-between gap-3">
                        <CardBackedBadge tone={data.tone}>{pluralDays(data.idleDays)} idle</CardBackedBadge>
                        <Link
                            href={data.href}
                            className={cn(
                                'inline-flex items-center gap-1.5 text-sm font-medium hover:underline',
                                LINK_TEXT[data.tone],
                            )}
                        >
                            Open document
                            <ArrowRight className="size-4" aria-hidden />
                        </Link>
                    </div>
                </>
            ) : (
                <p className="text-sm text-muted-foreground">Nothing is stuck right now.</p>
            )}
        </StatCard>
    );
}

const BUCKET_ROWS = [
    { key: 'under_7', label: 'Under 7 days', bar: 'bg-muted-foreground' },
    { key: '7_14', label: '7 to 14 days', bar: 'bg-muted-foreground' },
    { key: '15_30', label: '15 to 30 days', bar: 'bg-warning' },
    { key: 'over_30', label: 'Over 30 days', bar: 'bg-destructive' },
] as const;

function HowLongIdleCard({ stats }: { stats: StuckDocumentsStatsData }) {
    const max = Math.max(1, ...Object.values(stats.buckets));

    return (
        <StatCard icon={Clock} title="How long idle">
            <ul className="flex flex-col gap-2.5">
                {BUCKET_ROWS.map((row) => (
                    <li
                        key={row.key}
                        className="grid grid-cols-[minmax(0,1fr)_4.5rem_1.5rem] items-center gap-3 text-sm"
                    >
                        <span className="min-w-0 break-words">{row.label}</span>
                        <span aria-hidden className="h-1.5 overflow-hidden rounded-full bg-muted">
                            <span
                                className={cn('block h-full rounded-full', row.bar)}
                                style={{ width: `${(stats.buckets[row.key] / max) * 100}%` }}
                            />
                        </span>
                        <span className="text-right font-semibold tabular-nums">{stats.buckets[row.key]}</span>
                    </li>
                ))}
            </ul>
            <p className="mt-auto text-sm text-muted-foreground">
                Median wait:{' '}
                <strong className="font-semibold text-foreground tabular-nums">{pluralDays(stats.medianDays)}</strong>
            </p>
        </StatCard>
    );
}

function HoldingMostCard({ data }: { data: StuckDocumentsStatsData['holdingMost'] }) {
    return (
        <StatCard icon={UserRound} title="Holding the most">
            {data ? (
                <>
                    <p className="text-2xl leading-tight font-semibold break-words">{data.name}</p>
                    <dl className="mt-auto grid grid-cols-[auto_1fr] gap-x-6 gap-y-1.5 text-sm">
                        <dt className="text-muted-foreground">Stuck</dt>
                        <dd className="text-right font-semibold tabular-nums">
                            {data.stuck} {data.stuck === 1 ? 'document' : 'documents'}
                        </dd>
                        <dt className="text-muted-foreground">Role</dt>
                        <dd className="text-right font-semibold">{data.role}</dd>
                        <dt className="text-muted-foreground">Longest</dt>
                        <dd className="text-right font-semibold tabular-nums">{pluralDays(data.longestDays)}</dd>
                    </dl>
                </>
            ) : (
                <p className="text-sm text-muted-foreground">No approver is holding a document right now.</p>
            )}
        </StatCard>
    );
}

/** The four Stuck Documents cards. They cover every stuck document and never change with the filters. */
export default function StuckDocumentsStats({ stats }: { stats: StuckDocumentsStatsData }) {
    return (
        <section aria-label="Stuck document figures" className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StuckRightNowCard stats={stats} />
            <IdleLongestCard data={stats.idleLongest} />
            <HowLongIdleCard stats={stats} />
            <HoldingMostCard data={stats.holdingMost} />
        </section>
    );
}
