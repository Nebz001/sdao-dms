import { ToneBadge } from '@/components/status-badge';
import { labelFor, toneFor } from '@/lib/status-tones';
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
 * How long a document has been idle, toned by the tier the server computed
 * (InReviewSnapshot::tierFor(): green under 3 days, amber 3 to 7, red over 7;
 * the thresholds live only in PHP, and the tier to tone mapping lives in
 * lib/status-tones.ts). The number is always printed, so the tier is never
 * carried by color alone. It reads as a duration, so it stays in normal case
 * ("11 d idle", not "11 D IDLE") and uses a mono font.
 */
export default function IdleBadge({ days, tier, label = 'compact', className }: IdleBadgeProps) {
    return (
        <ToneBadge
            tone={toneFor('idle', tier)}
            className={cn('font-mono tabular-nums normal-case tracking-normal', className)}
        >
            <span aria-hidden>
                {days} d{label === 'idle' ? ' idle' : ''}
            </span>
            {/* A span cannot carry an aria-label, so the full reading is real text for assistive tech. */}
            <span className="sr-only">
                Idle for {days} {days === 1 ? 'day' : 'days'}, {labelFor('idle', tier).toLowerCase()}
            </span>
        </ToneBadge>
    );
}
