import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

type DateBadgeProps = {
    /** `"YYYY-MM-DD"`. */
    iso: string;
    className?: string;
};

/**
 * Splits `"YYYY-MM-DD"` into the two parts the badge shows. Parses by
 * component (year/month/day passed straight to `new Date(...)`) rather than
 * `new Date(isoString)` — the same UTC-midnight shift `formatCalendarDate`
 * and `lib/month-grid.ts` already avoid for the same reason.
 */
function splitDate(iso: string): { month: string; day: number } {
    const [year, month, day] = iso.split('-').map(Number);
    const date = new Date(year, month - 1, day);

    return {
        month: date
            .toLocaleDateString(undefined, { month: 'short' })
            .toUpperCase(),
        day: date.getDate(),
    };
}

/**
 * The month-over-day tile that anchors a row in an activity list (landing
 * page "Next up", dashboard "Coming up"). Read as plain text, so it needs no extra label.
 */
export default function DateBadge({
    iso,
    className,
    ...props
}: DateBadgeProps & Omit<ComponentProps<'div'>, 'className'>) {
    const { month, day } = splitDate(iso);

    return (
        <div
            className={cn(
                'flex size-12 shrink-0 flex-col items-center justify-center gap-0.5 rounded-md bg-brand-fixed leading-none text-brand-fixed-foreground',
                className,
            )}
            {...props}
        >
            {/* Tracking is tuned so a 3-letter month is as wide as a 2-digit
                day; the left padding cancels the trailing letter-space so
                both lines centre on the same axis. */}
            <span className="pl-[0.08em] text-[0.6875rem] font-semibold tracking-[0.08em] uppercase">
                {month}
            </span>
            <span className="text-xl font-bold tabular-nums">{day}</span>
        </div>
    );
}
