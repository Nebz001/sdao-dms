import { ToneBadge } from '@/components/status-badge';
import { cn } from '@/lib/utils';
import { pluralDays } from './types';
import type { WaitTier } from './types';
import { TIER_TONE } from './wait-tier';

/** How long a document has waited: red 8+ days, amber 3 to 7, grey 0 to 2. */
export default function WaitPill({
    days,
    tier,
    suffix,
    flagOverdue = false,
    className,
}: {
    days: number;
    tier: WaitTier;
    suffix?: string;
    /** Reads "9 days, overdue" when the tier is overdue. */
    flagOverdue?: boolean;
    className?: string;
}) {
    return (
        <ToneBadge
            tone={TIER_TONE[tier]}
            className={cn('text-xs tracking-normal tabular-nums normal-case', className)}
        >
            {pluralDays(days)}
            {suffix ? ` ${suffix}` : ''}
            {flagOverdue && tier === 'overdue' ? ', overdue' : ''}
        </ToneBadge>
    );
}
