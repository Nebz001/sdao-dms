import { ToneBadge } from '@/components/status-badge';
import { labelFor, toneFor } from '@/lib/status-tones';
import { cn } from '@/lib/utils';

type WaitTier = 'normal' | 'warning' | 'overdue';

type WaitBadgeProps = {
    days: number;
    tier: WaitTier;
    className?: string;
};

/**
 * How long a document has been waiting at this approver's step, toned by the
 * backend-computed tier (ApproverQueue::WARNING_AFTER_DAYS /
 * OVERDUE_AFTER_DAYS): the 2-day and 3-day thresholds live only in PHP, never
 * duplicated here, and the tier to tone mapping lives in lib/status-tones.ts.
 * Like the idle badge it is a duration, so it stays in normal case.
 */
export default function WaitBadge({ days, tier, className }: WaitBadgeProps) {
    return (
        <ToneBadge
            tone={toneFor('wait', tier)}
            className={cn('tabular-nums normal-case tracking-normal', className)}
        >
            <span aria-hidden>{days}d</span>
            <span className="sr-only">
                Waiting {days} {days === 1 ? 'day' : 'days'}, {labelFor('wait', tier).toLowerCase()}
            </span>
        </ToneBadge>
    );
}
