import { useMemo } from 'react';
import { buildMonthGrid, currentYearMonth } from '@/lib/month-grid';
import { cn, formatCalendarDate } from '@/lib/utils';
import type { PublicActivity } from '@/types/public-activity';

const WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

type Props = {
    /** Approved, upcoming activities — grouped here by `activity_date`. */
    activities: PublicActivity[];
    /** `"YYYY-MM-DD"` of the day the list is currently filtered to, or null. */
    selectedDate: string | null;
    onSelectDay: (iso: string) => void;
    /** `"YYYY-MM-DD"` to treat as "today"; defaults to the real current date. Overridable for tests. */
    today?: string;
};

function dayAriaLabel(iso: string, count: number): string {
    const label = formatCalendarDate(iso);

    if (count === 0) {
        return label;
    }

    return `${label}, ${count} ${count === 1 ? 'activity' : 'activities'}`;
}

/**
 * A compact, read-only month calendar for the public landing page — always
 * shows the current month (no prev/next navigation; nothing to navigate to,
 * since the widget only ever has this month plus a short runway beyond it).
 *
 * Unlike VenueMonthGrid (the authenticated tool this is modeled on), a day
 * cell is only a real `<button>` when it has at least one approved activity
 * — there's nothing to click into on an empty day, and this keeps the tab
 * order short and meaningful instead of 42 stops of mostly-empty days.
 */
export default function PublicMiniCalendar({
    activities,
    selectedDate,
    onSelectDay,
    today,
}: Props) {
    // Derived from `today` when given, rather than always reading the real
    // current date — there's no month navigation on this widget, so
    // "today" and "the displayed month" must never disagree. This also
    // makes the component fully controllable in tests via `today` alone,
    // the same single override VenueMonthGrid's own `today` prop offers.
    const { year, month } = useMemo(() => {
        if (today) {
            const [y, m] = today.split('-').map(Number);

            return { year: y, month: m - 1 };
        }

        return currentYearMonth();
    }, [today]);
    const days = useMemo(
        () => buildMonthGrid(year, month, today),
        [year, month, today],
    );

    const countsByDate = useMemo(() => {
        const counts: Record<string, number> = {};

        for (const activity of activities) {
            counts[activity.activity_date] =
                (counts[activity.activity_date] ?? 0) + 1;
        }

        return counts;
    }, [activities]);

    const monthLabel = new Date(year, month, 1).toLocaleDateString(
        undefined,
        { year: 'numeric', month: 'long' },
    );

    return (
        <div role="grid" aria-label={monthLabel}>
            <p className="mb-2 text-center text-sm font-medium lg:mb-3 lg:text-lg">
                {monthLabel}
            </p>

            <div
                role="row"
                className="grid grid-cols-7 gap-px text-center text-xs font-medium text-muted-foreground lg:text-sm"
            >
                {WEEKDAY_LABELS.map((label) => (
                    <div
                        key={label}
                        role="columnheader"
                        className="py-1 lg:py-2"
                    >
                        {label}
                    </div>
                ))}
            </div>

            <div className="grid grid-cols-7 gap-px overflow-hidden rounded-md border bg-border">
                {days.map((day) => {
                    const count = countsByDate[day.iso] ?? 0;
                    const hasActivity = count > 0;
                    const isSelected = day.iso === selectedDate;

                    const dayNumber = (
                        <span
                            className={cn(
                                'flex size-5 items-center justify-center rounded-full text-xs lg:size-9 lg:text-base',
                                day.isToday
                                    ? 'bg-brand font-semibold text-brand-foreground'
                                    : 'text-muted-foreground',
                                day.inMonth &&
                                    !day.isToday &&
                                    'text-foreground',
                            )}
                        >
                            {day.day}
                        </span>
                    );

                    const cellContent = (
                        <>
                            {dayNumber}
                            <span className="flex h-1.5 items-center justify-center lg:h-2">
                                {hasActivity && (
                                    <span
                                        aria-hidden="true"
                                        className="size-1.5 rounded-full bg-brand lg:size-2"
                                    />
                                )}
                            </span>
                        </>
                    );

                    const cellClassName = cn(
                        'flex min-h-11 flex-col items-center justify-start gap-0.5 bg-card py-1 transition-colors sm:min-h-12 lg:min-h-20 lg:justify-center lg:gap-1',
                        !day.inMonth && 'bg-muted/40',
                        isSelected && 'ring-2 ring-primary ring-inset',
                    );

                    if (!hasActivity) {
                        return (
                            <div
                                key={day.iso}
                                role="gridcell"
                                aria-label={dayAriaLabel(day.iso, count)}
                                className={cellClassName}
                            >
                                {cellContent}
                            </div>
                        );
                    }

                    return (
                        <button
                            key={day.iso}
                            type="button"
                            role="gridcell"
                            aria-pressed={isSelected}
                            aria-label={dayAriaLabel(day.iso, count)}
                            onClick={() => onSelectDay(day.iso)}
                            className={cn(
                                cellClassName,
                                'cursor-pointer hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset',
                            )}
                        >
                            {cellContent}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
