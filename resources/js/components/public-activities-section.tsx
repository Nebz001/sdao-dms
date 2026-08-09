import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import PublicActivityRow from '@/components/public-activity-row';
import PublicMiniCalendar from '@/components/public-mini-calendar';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatCalendarDate } from '@/lib/utils';
import * as calendar from '@/routes/calendar';
import type { PublicActivity } from '@/types/public-activity';

type Props = {
    activities: PublicActivity[];
};

/**
 * Rows per "Next up" page — sized to comfortably fit within the calendar
 * card's height (see `calendarHeight` below) at the current row size.
 */
const PAGE_SIZE = 5;

/**
 * The landing page's public activities widget — an "Upcoming Activities"
 * list next to a compact current-month calendar, replacing the old static
 * "Who this is for" band. Every activity here is Approved, filtered
 * server-side (see App\Http\Controllers\HomeController) — this component
 * has no filtering responsibility of its own beyond the day-selection UI.
 *
 * Selecting a day on PublicMiniCalendar filters the list to that day
 * (clicking the same day again clears it) rather than scrolling to it —
 * the list only ever shows a bounded slice of `activities`, so a day's
 * activities might not be in the rendered slice to scroll to at all.
 * Selecting (or clearing) a day always resets pagination to page 1 of
 * whichever set — full or filtered — is now active, so a stale page
 * number can never point past the end of a newly-filtered list.
 */
export default function PublicActivitiesSection({ activities }: Props) {
    const [selectedDate, setSelectedDate] = useState<string | null>(null);
    const [page, setPage] = useState(1);

    // "Next up" is pinned to the calendar card's own rendered height (not a
    // guessed number) so the pair reads as matched regardless of how many
    // rows are on the current page. Re-measured via getBoundingClientRect
    // (not ResizeObserver's contentRect) because contentRect excludes the
    // card's own padding/border — exactly the parts that make up the
    // visible card height. Only applied at `lg` and up (--calendar-height
    // is read by an `lg:` class below): below that the cards stack, and
    // forcing "Next up" to the (smaller, unscaled) mobile calendar height
    // would just clip content for no visual benefit.
    const calendarCardRef = useRef<HTMLDivElement>(null);
    const [calendarHeight, setCalendarHeight] = useState<number | null>(null);

    useEffect(() => {
        const el = calendarCardRef.current;

        if (!el) {
            return;
        }

        const observer = new ResizeObserver(() => {
            setCalendarHeight(el.getBoundingClientRect().height);
        });

        observer.observe(el);

        return () => observer.disconnect();
    }, []);

    function handleSelectDay(iso: string) {
        setSelectedDate((current) => (current === iso ? null : iso));
        setPage(1);
    }

    function clearFilter() {
        setSelectedDate(null);
        setPage(1);
    }

    const filteredActivities = useMemo(() => {
        if (!selectedDate) {
            return activities;
        }

        return activities.filter(
            (activity) => activity.activity_date === selectedDate,
        );
    }, [activities, selectedDate]);

    const totalPages = Math.max(
        1,
        Math.ceil(filteredActivities.length / PAGE_SIZE),
    );
    const currentPage = Math.min(page, totalPages);

    const visibleActivities = useMemo(() => {
        const start = (currentPage - 1) * PAGE_SIZE;

        return filteredActivities.slice(start, start + PAGE_SIZE);
    }, [filteredActivities, currentPage]);

    return (
        <section className="mx-auto w-full max-w-7xl px-6 py-14">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p className="text-xs font-semibold tracking-[0.2em] text-brand uppercase">
                        Stay in the loop
                    </p>
                    <h2 className="mt-1 text-2xl font-bold tracking-tight text-balance sm:text-3xl">
                        Upcoming Activities
                    </h2>
                    <p className="mt-2 max-w-prose text-sm text-foreground/80">
                        Approved activities from student organizations
                        across NU Lipa.
                    </p>
                </div>
                <Button variant="outline" size="sm" asChild>
                    <Link href={calendar.index()}>View full calendar</Link>
                </Button>
            </div>

            {activities.length === 0 ? (
                <Card className="mt-6">
                    <CardContent className="flex flex-col items-center gap-1 py-10 text-center">
                        <p className="text-sm font-medium">
                            No upcoming activities right now
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Check back soon, or browse the full calendar
                            below.
                        </p>
                    </CardContent>
                </Card>
            ) : (
                <div
                    className="mt-6 grid grid-cols-1 items-start gap-6 lg:grid-cols-2"
                    style={
                        calendarHeight
                            ? ({
                                  '--calendar-height': `${calendarHeight}px`,
                              } as React.CSSProperties)
                            : undefined
                    }
                >
                    <Card className="lg:h-[var(--calendar-height)]">
                        <CardHeader>
                            <CardTitle className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                {selectedDate
                                    ? `Activities on ${formatCalendarDate(selectedDate)}`
                                    : 'Next up'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-1 flex-col gap-2 overflow-hidden">
                            {selectedDate && (
                                <p
                                    aria-live="polite"
                                    className="pb-1 text-xs text-muted-foreground"
                                >
                                    Showing activities on{' '}
                                    {formatCalendarDate(selectedDate)}.{' '}
                                    <button
                                        type="button"
                                        onClick={clearFilter}
                                        className="font-medium text-primary underline-offset-2 hover:underline"
                                    >
                                        Clear filter
                                    </button>
                                </p>
                            )}
                            {visibleActivities.length === 0 ? (
                                <p className="py-4 text-center text-sm text-muted-foreground">
                                    No activities on this day.
                                </p>
                            ) : (
                                visibleActivities.map((activity) => (
                                    <PublicActivityRow
                                        key={activity.id}
                                        activity={activity}
                                    />
                                ))
                            )}

                            {totalPages > 1 && (
                                <div className="mt-auto flex items-center justify-center gap-3 pt-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        disabled={currentPage === 1}
                                        onClick={() =>
                                            setPage((p) => Math.max(1, p - 1))
                                        }
                                        aria-label="Previous page"
                                    >
                                        <ChevronLeft />
                                    </Button>
                                    <span className="text-xs text-muted-foreground tabular-nums">
                                        Page {currentPage} of {totalPages}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        disabled={currentPage === totalPages}
                                        onClick={() =>
                                            setPage((p) =>
                                                Math.min(totalPages, p + 1),
                                            )
                                        }
                                        aria-label="Next page"
                                    >
                                        <ChevronRight />
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card ref={calendarCardRef}>
                        <CardHeader>
                            <CardTitle className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                This Month
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <PublicMiniCalendar
                                activities={activities}
                                selectedDate={selectedDate}
                                onSelectDay={handleSelectDay}
                            />
                        </CardContent>
                    </Card>
                </div>
            )}
        </section>
    );
}
