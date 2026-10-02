import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type IdleTier = 'fresh' | 'aging' | 'stale';

type IdleBadgeProps = {
    days: number;
    tier: IdleTier;
    /** "compact" reads "11 d"; "idle" reads "11 d idle". */
    label?: 'compact' | 'idle';
    className?: string;
};

/**
 * How long a document has been idle, colored by the tier the server computed
 * (InReviewSnapshot::tierFor(): green under 3 days, amber 3 to 7, red over 7;
 * the thresholds live only in PHP). The number is always printed, so the tier
 * is never carried by color alone. A tinted fill with the semantic
 * "-foreground" text token keeps the text readable in both themes.
 */
const TIER_STYLES: Record<IdleTier, string> = {
    fresh: 'border-success/40 bg-success/10 text-success-foreground',
    aging: 'border-warning/40 bg-warning/10 text-warning-foreground',
    stale: 'border-destructive/40 bg-destructive/10 text-destructive-foreground',
};

const TIER_NAME: Record<IdleTier, string> = {
    fresh: 'recent',
    aging: 'getting old',
    stale: 'overdue',
};

export default function IdleBadge({ days, tier, label = 'compact', className }: IdleBadgeProps) {
    return (
        <Badge
            variant="outline"
            className={cn('font-mono font-semibold tabular-nums', TIER_STYLES[tier], className)}
        >
            <span aria-hidden>
                {days} d{label === 'idle' ? ' idle' : ''}
            </span>
            {/* A span cannot carry an aria-label, so the full reading is real text for assistive tech. */}
            <span className="sr-only">
                Idle for {days} {days === 1 ? 'day' : 'days'}, {TIER_NAME[tier]}
            </span>
        </Badge>
    );
}
