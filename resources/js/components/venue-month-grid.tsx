import { useMemo, useRef, useState } from 'react';
import { ToneBadge } from '@/components/status-badge';
import { buildMonthGrid } from '@/lib/month-grid';
import { toneFor } from '@/lib/status-tones';
import { cn, formatCalendarDate } from '@/lib/utils';
import type { VenueBooking } from '@/types/venue-calendar';

const WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const MAX_CHIPS_PER_DAY = 2;

type Props = {
    /** Full year, e.g. 2026. */
    year: number;
    /** 0-indexed month (0 = January). */
    month: number;
    /** Bookings grouped by `"YYYY-MM-DD"`. */
    bookingsByDate: Record<string, VenueBooking[]>;
    /** `"YYYY-MM-DD"` of the day currently shown in the detail panel, or null. */
    selectedDate: string | null;
    onSelectDay: (iso: string) => void;
    /** `"YYYY-MM-DD"` to treat as "today"; defaults to the real current date. Overridable for tests. */
    today?: string;
};

function dayAriaLabel(iso: string, bookings: VenueBooking[]): string {
    const label = formatCalendarDate(iso);

    if (bookings.length === 0) {
        return `${label}, no bookings`;
    }

    const confirmed = bookings.filter((b) => b.status === 'approved').length;
    const tentative = bookings.length - confirmed;
    const parts: string[] = [];

    if (confirmed > 0) {
        parts.push(`${confirmed} confirmed`);
    }

    if (tentative > 0) {
        parts.push(`${tentative} tentative`);
    }

    return `${label}, ${parts.join(', ')}`;
}

/**
 * Month grid for the shared venue calendar. Presentational — the caller owns
 * which month is displayed and which day (if any) is selected; this only
 * renders that state and reports clicks/keyboard activation back up via
 * `onSelectDay`.
 *
 * Each day cell shows up to `MAX_CHIPS_PER_DAY` bookings as chips (confirmed
 * bookings first, since they're the higher-priority "this actually blocks
 * the venue" information), then a "+N more" overflow marker. Confirmed and
 * tentative chips are never distinguished by color alone: confirmed uses a
 * solid tinted fill, tentative a dashed outline — the same solid-vs-outline
 * language `activity-proposals/create.tsx` and `activity-calendars/create.tsx`
 * already use for their live conflict-check results.
 */
export default function VenueMonthGrid({
    year,
    month,
    bookingsByDate,
    selectedDate,
    onSelectDay,
    today,
}: Props) {
    const days = useMemo(
        () => buildMonthGrid(year, month, today),
        [year, month, today],
    );
    const cellRefs = useRef<Array<HTMLButtonElement | null>>([]);

    const initialFocusIndex = useMemo(() => {
        const todayIndex = days.findIndex((d) => d.inMonth && d.isToday);

        if (todayIndex >= 0) {
            return todayIndex;
        }

        const firstInMonth = days.findIndex((d) => d.inMonth);

        return firstInMonth >= 0 ? firstInMonth : 0;
        // Recomputed only when the displayed month changes, not on every
        // bookings update — see the effect below.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [year, month]);

    const [focusedIndex, setFocusedIndex] = useState(initialFocusIndex);

    // Resets the roving-tabindex focus target when the displayed month
    // changes (e.g. Prev/Next), without an effect — an effect would commit
    // the stale month's grid first and only fix the focus target a render
    // later. This is React's documented "adjust state during render"
    // pattern: comparing against a piece of state derived from props and
    // calling setState conditionally, gated so it fires at most once per
    // month change. https://react.dev/reference/react/useState#storing-information-from-previous-renders
    const [renderedMonthKey, setRenderedMonthKey] = useState(
        `${year}-${month}`,
    );
    const monthKey = `${year}-${month}`;

    if (monthKey !== renderedMonthKey) {
        setRenderedMonthKey(monthKey);
        setFocusedIndex(initialFocusIndex);
    }

    function moveFocus(fromIndex: number, delta: number) {
        const next = fromIndex + delta;

        if (next < 0 || next >= days.length) {
            return;
        }

        setFocusedIndex(next);
        cellRefs.current[next]?.focus();
    }

    function handleKeyDown(
        event: React.KeyboardEvent<HTMLButtonElement>,
        index: number,
    ) {
        switch (event.key) {
            case 'ArrowRight':
                event.preventDefault();
                moveFocus(index, 1);
                break;
            case 'ArrowLeft':
                event.preventDefault();
                moveFocus(index, -1);
                break;
            case 'ArrowDown':
                event.preventDefault();
                moveFocus(index, 7);
                break;
            case 'ArrowUp':
                event.preventDefault();
                moveFocus(index, -7);
                break;
            case 'Home':
                event.preventDefault();
                moveFocus(index, -(index % 7));
                break;
            case 'End':
                event.preventDefault();
                moveFocus(index, 6 - (index % 7));
                break;
        }
    }

    const monthLabel = new Date(year, month, 1).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'long',
    });

    return (
        <div role="grid" aria-label={monthLabel}>
            <div
                role="row"
                className="mb-1 grid grid-cols-7 gap-px text-center text-[0.65rem] font-medium tracking-wider text-muted-foreground uppercase sm:text-xs"
            >
                {WEEKDAY_LABELS.map((label) => (
                    <div key={label} role="columnheader" className="py-1.5">
                        {label}
                    </div>
                ))}
            </div>

            <div className="grid grid-cols-7 gap-px overflow-hidden rounded-lg border bg-border/60">
                {days.map((day, index) => {
                    const bookings = bookingsByDate[day.iso] ?? [];
                    const sorted = [...bookings].sort((a, b) =>
                        a.status === b.status
                            ? 0
                            : a.status === 'approved'
                              ? -1
                              : 1,
                    );
                    const visible = sorted.slice(0, MAX_CHIPS_PER_DAY);
                    const overflowCount = sorted.length - visible.length;
                    const isSelected = day.iso === selectedDate;

                    return (
                        <button
                            key={day.iso}
                            ref={(el) => {
                                cellRefs.current[index] = el;
                            }}
                            type="button"
                            role="gridcell"
                            tabIndex={focusedIndex === index ? 0 : -1}
                            aria-pressed={isSelected}
                            aria-label={dayAriaLabel(day.iso, bookings)}
                            onFocus={() => setFocusedIndex(index)}
                            onKeyDown={(e) => handleKeyDown(e, index)}
                            onClick={() => onSelectDay(day.iso)}
                            className={cn(
                                'flex h-[5.5rem] flex-col items-stretch gap-1 overflow-hidden bg-card p-1 text-left align-top transition-colors sm:h-28 sm:p-1',
                                'hover:bg-accent focus-visible:focus-ring-inset',
                                !day.inMonth && 'bg-muted/30 opacity-60',
                                isSelected &&
                                    'ring-2 ring-primary-text ring-inset',
                            )}
                        >
                            <span
                                className={cn(
                                    'flex size-6 shrink-0 items-center justify-center self-start rounded-full text-xs font-medium',
                                    isSelected
                                        ? 'bg-primary text-primary-foreground'
                                        : day.isToday
                                          ? 'border border-primary-text font-semibold text-primary-text'
                                          : day.inMonth
                                            ? 'text-foreground'
                                            : 'text-muted-foreground',
                                )}
                            >
                                {day.day}
                            </span>

                            <div className="flex min-h-0 flex-1 flex-col gap-1 overflow-hidden">
                                {visible.map((booking) => (
                                    <ToneBadge
                                        key={booking.id}
                                        tone={toneFor(
                                            'venue',
                                            booking.status === 'approved'
                                                ? 'confirmed'
                                                : 'tentative',
                                        )}
                                        className={cn(
                                            'line-clamp-2 w-full rounded-md px-1 py-0.5 text-left text-[0.625rem] leading-3.5 font-medium tracking-normal break-words whitespace-normal normal-case max-sm:h-2.5 max-sm:overflow-hidden max-sm:p-0 max-sm:text-transparent',
                                            // A tentative booking keeps a dashed edge, so it differs from a confirmed
                                            // one by shape as well as by color.
                                            booking.status !== 'approved' &&
                                                'border-dashed',
                                        )}
                                    >
                                        {booking.venue}
                                    </ToneBadge>
                                ))}
                                {overflowCount > 0 && (
                                    <span className="px-0.5 text-[0.65rem] font-medium text-muted-foreground">
                                        +{overflowCount} more
                                    </span>
                                )}
                            </div>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
