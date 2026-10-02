import { Link } from '@inertiajs/react';
import { ArrowRight, CalendarSearch } from 'lucide-react';
import FadeScroll from '@/components/fade-scroll';
import { VenueStatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { cn, formatTimeRange } from '@/lib/utils';
import type { ComingUp } from '@/lib/venue-calendar';
import type { VenueBooking } from '@/types/venue-calendar';

const statusOf = (booking: VenueBooking) =>
    booking.status === 'approved' ? 'confirmed' : 'tentative';

function longDate(iso: string): string {
    const [year, month, day] = iso.split('-').map(Number);

    return new Date(year, month - 1, day).toLocaleDateString(undefined, {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
    });
}

type DayCardProps = {
    selectedDate: string | null;
    bookings: VenueBooking[];
    /** Where "View proposal" goes for a booking, or null when the viewer may not open it. */
    hrefFor: (booking: VenueBooking) => string | null;
};

function DetailRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-baseline gap-4 text-sm">
            <dt className="w-24 shrink-0 text-muted-foreground">{label}</dt>
            <dd className="min-w-0 font-medium [overflow-wrap:anywhere]">
                {value}
            </dd>
        </div>
    );
}

/**
 * The selected-day card: the heading and count stay fixed, the bookings
 * scroll. Below the xl breakpoint it is capped at 360px; on xl it takes up to
 * 55% of the right column. One booking shrinks it to fit its content.
 */
export function SelectedDayCard({
    selectedDate,
    bookings,
    hrefFor,
}: DayCardProps) {
    return (
        <Card
            aria-live="polite"
            className="max-h-90 min-h-0 shrink-0 gap-4 xl:max-h-[55%]"
        >
            <CardHeader className="shrink-0">
                <CardTitle className="text-xl">
                    {selectedDate ? longDate(selectedDate) : 'No day selected'}
                </CardTitle>
                <CardDescription>
                    {!selectedDate
                        ? 'Select a day to see its bookings.'
                        : bookings.length === 0
                          ? 'No bookings on this day'
                          : `${bookings.length} ${bookings.length === 1 ? 'booking' : 'bookings'} on this day`}
                </CardDescription>
            </CardHeader>
            {bookings.length > 0 && (
                <CardContent className="flex min-h-0 flex-1 flex-col px-0">
                    <FadeScroll label="Bookings on the selected day">
                        <div className="flex flex-col gap-3 px-6">
                            {bookings.map((booking) => {
                                const href = hrefFor(booking);

                                return (
                                    <article
                                        key={booking.id}
                                        className="flex flex-col gap-3 rounded-lg border bg-muted/30 p-4"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <h3 className="min-w-0 text-base leading-snug font-semibold [overflow-wrap:anywhere]">
                                                {booking.name}
                                            </h3>
                                            <VenueStatusBadge
                                                status={statusOf(booking)}
                                                className="shrink-0 whitespace-nowrap"
                                            />
                                        </div>
                                        <dl className="flex flex-col gap-1.5">
                                            <DetailRow
                                                label="Organization"
                                                value={booking.organization}
                                            />
                                            <DetailRow
                                                label="Time"
                                                value={formatTimeRange(
                                                    booking.start_time,
                                                    booking.end_time,
                                                ).replace('–', 'to')}
                                            />
                                            <DetailRow
                                                label="Venue"
                                                value={booking.venue}
                                            />
                                        </dl>
                                        {href && (
                                            <Link
                                                href={href}
                                                className="inline-flex w-fit items-center gap-1.5 rounded-sm text-sm font-medium text-primary-text underline-offset-4 hover:underline focus-visible:focus-ring-edge"
                                            >
                                                View proposal
                                                <ArrowRight
                                                    className="size-4"
                                                    aria-hidden
                                                />
                                            </Link>
                                        )}
                                    </article>
                                );
                            })}
                        </div>
                    </FadeScroll>
                </CardContent>
            )}
        </Card>
    );
}

type ComingUpProps = {
    comingUp: ComingUp;
    selectedDate: string | null;
    onSelectDay: (iso: string) => void;
    /** Switches the page to the List view; filters stay as they are. */
    onViewAll: () => void;
};

/**
 * The next bookings from today onward, excluding the selected day (its card
 * already shows them). The title stays fixed; only the list scrolls. On xl it
 * fills the rest of the right column, below that it is capped at 480px.
 */
export function ComingUpCard({
    comingUp,
    selectedDate,
    onSelectDay,
    onViewAll,
}: ComingUpProps) {
    const { items, hasMore, hiddenOnSelectedDay } = comingUp;

    return (
        <Card className="max-h-120 min-h-0 gap-4 xl:max-h-none xl:flex-1">
            <CardHeader className="shrink-0">
                <CardTitle className="text-xl">Coming up</CardTitle>
            </CardHeader>
            <CardContent className="flex min-h-0 flex-1 flex-col px-0">
                {items.length === 0 ? (
                    <div className="flex items-center gap-2 px-6 text-sm text-muted-foreground">
                        <CalendarSearch className="size-4" aria-hidden />
                        {hiddenOnSelectedDay > 0
                            ? 'No other upcoming bookings'
                            : 'No upcoming bookings'}
                    </div>
                ) : (
                    <FadeScroll label="Upcoming bookings">
                        <ul className="flex flex-col px-6">
                            {items.map((booking) => {
                                const [year, month, day] = booking.activity_date
                                    .split('-')
                                    .map(Number);
                                const monthLabel = new Date(
                                    year,
                                    month - 1,
                                    day,
                                ).toLocaleDateString(undefined, {
                                    month: 'short',
                                });

                                return (
                                    <li
                                        key={booking.id}
                                        className="border-b last:border-b-0"
                                    >
                                        <button
                                            type="button"
                                            onClick={() =>
                                                onSelectDay(
                                                    booking.activity_date,
                                                )
                                            }
                                            aria-label={`${booking.name}, ${longDate(booking.activity_date)}. Show this day.`}
                                            className={cn(
                                                '-mx-2 flex w-[calc(100%+1rem)] items-start gap-3 rounded-md px-2 py-3 text-left transition-colors hover:bg-accent focus-visible:focus-ring-edge',
                                                selectedDate ===
                                                    booking.activity_date &&
                                                    'bg-accent/60',
                                            )}
                                        >
                                            <span
                                                aria-hidden
                                                className="flex size-11 shrink-0 flex-col items-center justify-center rounded-md border bg-muted/40 leading-none"
                                            >
                                                <span className="text-[0.65rem] tracking-wide text-muted-foreground uppercase">
                                                    {monthLabel}
                                                </span>
                                                <span className="mt-0.5 text-base font-bold">
                                                    {day}
                                                </span>
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="line-clamp-2 text-sm leading-snug font-semibold [overflow-wrap:anywhere]">
                                                    {booking.name}
                                                </span>
                                                <span className="line-clamp-1 text-xs [overflow-wrap:anywhere] text-muted-foreground">
                                                    {booking.venue}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {formatTimeRange(
                                                        booking.start_time,
                                                        booking.end_time,
                                                    ).replace('–', 'to')}
                                                </span>
                                            </span>
                                            <VenueStatusBadge
                                                status={statusOf(booking)}
                                                className="shrink-0 whitespace-nowrap"
                                            />
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    </FadeScroll>
                )}
                {hasMore && (
                    <div className="shrink-0 px-6 pt-3">
                        <Button
                            variant="link"
                            className="h-auto p-0"
                            onClick={onViewAll}
                        >
                            View all in List view
                            <ArrowRight data-icon="inline-end" />
                        </Button>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

/** Placeholder for the two side cards while the calendar data loads; rows fill the scroll area. */
export function SideCardSkeleton({
    rows,
    className,
}: {
    rows: number;
    className?: string;
}) {
    return (
        <Card aria-busy="true" className={cn('min-h-0 gap-4', className)}>
            <CardHeader className="shrink-0">
                <Skeleton className="h-6 w-2/3" />
                <Skeleton className="h-4 w-1/3" />
            </CardHeader>
            <CardContent className="flex min-h-0 flex-1 flex-col gap-3 overflow-hidden">
                {Array.from({ length: rows }, (_, i) => (
                    <Skeleton key={i} className="h-16 w-full shrink-0" />
                ))}
            </CardContent>
        </Card>
    );
}

export function CalendarSkeleton() {
    return (
        <Card aria-busy="true">
            <CardHeader>
                <Skeleton className="h-9 w-2/3" />
            </CardHeader>
            <CardContent>
                <Skeleton className="h-[34rem] w-full" />
            </CardContent>
        </Card>
    );
}
