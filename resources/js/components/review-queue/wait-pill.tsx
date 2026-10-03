import { ToneBadge } from '@/components/status-badge';
import type { Tone } from '@/lib/status-tones';
import { cn } from '@/lib/utils';
import { pluralDays } from './types';
import type { WaitTier } from './types';

const TIER_TONE: Record<WaitTier, Tone> = {
    overdue: 'destructive',
    aging: 'warning',
    fresh: 'neutral',
};

/** How long a document has waited: red 8+ days, amber 3 to 7, grey 0 to 2. */
export default function WaitPill({
    days,
    tier,
    suffix,
    className,
}: {
    days: number;
    tier: WaitTier;
    suffix?: string;
    className?: string;
}) {
    return (
        <ToneBadge
            tone={TIER_TONE[tier]}
            className={cn('text-xs tracking-normal tabular-nums normal-case', className)}
        >
            {pluralDays(days)}
            {suffix ? ` ${suffix}` : ''}
        </ToneBadge>
    );
}
