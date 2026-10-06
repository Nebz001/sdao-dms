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
export default function DateBadge({ iso, className }: DateBadgeProps) {
    const { month, day } = splitDate(iso);

    return (
        <div
            className={cn(
                'flex size-12 shrink-0 flex-col items-center justify-center rounded-md bg-brand-fixed text-brand-fixed-foreground',
                className,
            )}
        >
            <span className="text-[0.625rem] font-semibold tracking-wide uppercase">
                {month}
            </span>
            <span className="text-xl leading-none font-bold">{day}</span>
        </div>
    );
}
