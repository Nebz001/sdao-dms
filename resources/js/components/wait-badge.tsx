import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type WaitTier = 'normal' | 'warning' | 'overdue';

type WaitBadgeProps = {
    days: number;
    tier: WaitTier;
    className?: string;
};

/**
 * How long a document has been waiting at this approver's step, colored by
 * the backend-computed tier (ApproverQueue::WARNING_AFTER_DAYS /
 * OVERDUE_AFTER_DAYS) — the 2-day/3-day thresholds live only in PHP, never
 * duplicated here. Shares StatusBadge's exact solid-chip recipe and color
 * family (warning=amber, overdue=destructive/red) so "waiting too long"
 * reads with the same urgency language as every other badge in the app.
 */
const TIER_STYLES: Record<WaitTier, string> = {
    normal: 'border-transparent bg-muted text-muted-foreground',
    warning: 'border-transparent bg-warning text-background',
    overdue: 'border-transparent bg-destructive text-white',
};

const TIER_LABEL: Record<WaitTier, string> = {
    normal: 'on track',
    warning: 'getting close',
    overdue: 'overdue',
};

export default function WaitBadge({ days, tier, className }: WaitBadgeProps) {
    return (
        <Badge
            variant="outline"
            aria-label={`Waiting ${days} day${days === 1 ? '' : 's'} (${TIER_LABEL[tier]})`}
            className={cn(
                'rounded-full font-semibold tabular-nums',
                TIER_STYLES[tier],
                className,
            )}
        >
            {days}d
        </Badge>
    );
}
