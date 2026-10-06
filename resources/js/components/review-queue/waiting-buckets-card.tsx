import { UserRoundSearch } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import SegmentedBar from '@/components/review-queue/segmented-bar';
import StatCard, { StatValue } from '@/components/review-queue/stat-card';
import type { WaitTier } from '@/components/review-queue/types';
import { ToneBadge } from '@/components/status-badge';
import type { Tone } from '@/lib/status-tones';

export type WaitBuckets = Record<WaitTier, number>;

const BUCKET_LABEL: Record<WaitTier, string> = {
    fresh: '0 to 2 days',
    aging: '3 to 7 days',
    overdue: '8+ days',
};

const BUCKET_TONE: Record<WaitTier, Tone> = {
    fresh: 'neutral',
    aging: 'warning',
    overdue: 'destructive',
};

/** The longest wait present, so the card flags the worst of the queue. */
function worstBucket(buckets: WaitBuckets): { tier: WaitTier; count: number } | null {
    const tier = (['overdue', 'aging', 'fresh'] as const).find((t) => buckets[t] > 0);

    return tier ? { tier, count: buckets[tier] } : null;
}

/**
 * The "waiting" stat card of a people queue: the pending count, a badge
 * for the longest wait present, and a bar of how long everyone has waited.
 * Shared by Pending Accounts and Join Requests so they never drift apart.
 */
export default function WaitingBucketsCard({
    title,
    buckets,
    total,
    icon = UserRoundSearch,
}: {
    title: string;
    buckets: WaitBuckets;
    total: number;
    icon?: LucideIcon;
}) {
    const worst = worstBucket(buckets);

    return (
        <StatCard icon={icon} title={title}>
            <div className="flex items-center justify-between gap-3">
                <StatValue>{total}</StatValue>
                {worst && (
                    <ToneBadge tone={BUCKET_TONE[worst.tier]} className="text-xs tracking-normal normal-case tabular-nums">
                        {worst.count === total ? 'All' : worst.count} {BUCKET_LABEL[worst.tier]}
                    </ToneBadge>
                )}
            </div>
            <SegmentedBar
                ariaLabel={`Waiting time: ${buckets.fresh} at ${BUCKET_LABEL.fresh}, ${buckets.aging} at ${BUCKET_LABEL.aging}, ${buckets.overdue} at ${BUCKET_LABEL.overdue}`}
                segments={[
                    { label: BUCKET_LABEL.fresh, count: buckets.fresh, className: 'bg-info' },
                    { label: BUCKET_LABEL.aging, count: buckets.aging, className: 'bg-warning' },
                    { label: BUCKET_LABEL.overdue, count: buckets.overdue, className: 'bg-destructive' },
                ]}
            />
        </StatCard>
    );
}
