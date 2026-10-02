import { cn } from '@/lib/utils';

export type MeterTone = 'info' | 'success' | 'warning' | 'destructive';

const TONE_FILL: Record<MeterTone, string> = {
    info: 'bg-info',
    success: 'bg-success',
    warning: 'bg-warning',
    destructive: 'bg-destructive',
};

type MeterBarProps = {
    /** 0 to 100. */
    value: number;
    tone: MeterTone;
    /** Names what the bar measures, e.g. "Objectives: 42% of returns". */
    label: string;
    className?: string;
};

/**
 * A thin percent bar. The value is always printed next to it by the caller,
 * so the fill color is never the only signal; `role="meter"` exposes the
 * value to assistive tech. Built from existing tokens, no extra dependency.
 */
export default function MeterBar({ value, tone, label, className }: MeterBarProps) {
    const clamped = Math.min(100, Math.max(0, value));

    return (
        <div
            role="meter"
            aria-label={label}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={clamped}
            className={cn('h-1.5 w-full overflow-hidden rounded-full bg-muted', className)}
        >
            <div className={cn('h-full rounded-full', TONE_FILL[tone])} style={{ width: `${clamped}%` }} />
        </div>
    );
}
