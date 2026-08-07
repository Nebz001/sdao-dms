import { Head } from '@inertiajs/react';
import { CalendarX2, ChevronLeft, ChevronRight } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import VenueBookingRow from '@/components/venue-booking-row';
import VenueMonthGrid from '@/components/venue-month-grid';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import { addMonths, currentYearMonth } from '@/lib/month-grid';
import { formatCalendarDate } from '@/lib/utils';
import type { VenueBooking } from '@/types/venue-calendar';

type Props = {
    activities: VenueBooking[];
};

const ALL_VENUES = 'all';

/**
 * Shared venue calendar — confirmed (approved) and tentative (in_review)
 * activities. Defaults to a month grid (occupancy at a glance); the original
 * venue-grouped list stays available as a secondary view. Read-only: this
 * page displays existing bookings only, it doesn't create or edit them.
 */
export default function CalendarIndex({ activities }: Props) {
    useDocumentUpdates(['activities']);

    const [view, setView] = useState<'month' | 'list'>('month');
    const [{ year, month }, setYearMonth] = useState(currentYearMonth());
    const [selectedVenue, setSelectedVenue] = useState<string>(ALL_VENUES);
    const [selectedDate, setSelectedDate] = useState<string | null>(null);

    const venues = useMemo(
        () => Array.from(new Set(activities.map((a) => a.venue))).sort(),
        [activities],
    );

    const filteredActivities = useMemo(
        () =>
            selectedVenue === ALL_VENUES
                ? activities
                : activities.filter((a) => a.venue === selectedVenue),
        [activities, selectedVenue],
    );

    // Month view: bookings bucketed by date, for VenueMonthGrid's day cells.
    const bookingsByDate = useMemo(() => {
        const map: Record<string, VenueBooking[]> = {};

        for (const activity of filteredActivities) {
            (map[activity.activity_date] ??= []).push(activity);
        }

        return map;
    }, [filteredActivities]);

    // List view: venue -> date -> bookings (unchanged from the original grouping).
    const groupedByVenue = useMemo(() => {
        const grouped: Record<string, Record<string, VenueBooking[]>> = {};

        for (const activity of filteredActivities) {
            ((grouped[activity.venue] ??= {})[activity.activity_date] ??=
                []).push(activity);
        }

        return grouped;
    }, [filteredActivities]);

    const selectedDayBookings = selectedDate
        ? (bookingsByDate[selectedDate] ?? [])
        : [];
    const { year: currentYear, month: currentMonth } = currentYearMonth();
    const isCurrentMonth = year === currentYear && month === currentMonth;

    function goToMonth(delta: number) {
        setYearMonth((prev) => addMonths(prev.year, prev.month, delta));
    }

    function goToToday() {
        setYearMonth(currentYearMonth());
    }

    // Clicking the already-selected day closes the detail panel again.
    function handleSelectDay(iso: string) {
        setSelectedDate((current) => (current === iso ? null : iso));
    }

    return (
        <>
            <Head title="Venue Calendar" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-balance">
                            Venue Calendar
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Confirmed (approved) and tentative (under review)
                            activity bookings across all organizations. Venue
                            names are matched exactly — same spelling required
                            to detect conflicts.
                        </p>
                    </div>

                    <ToggleGroup
                        type="single"
                        value={view}
                        onValueChange={(value) =>
                            value && setView(value as 'month' | 'list')
                        }
                        variant="outline"
                        aria-label="Calendar view"
                    >
                        <ToggleGroupItem value="month" aria-label="Month view">
                            Month
                        </ToggleGroupItem>
                        <ToggleGroupItem value="list" aria-label="List view">
                            List
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>

                {view === 'month' ? (
                    <div className="grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                        <Card>
                            <CardHeader className="flex-row flex-wrap items-center justify-between gap-4">
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        onClick={() => goToMonth(-1)}
                                        aria-label="Previous month"
                                    >
                                        <ChevronLeft />
                                    </Button>
                                    <CardTitle className="min-w-40 text-center text-base">
                                        {new Date(
                                            year,
                                            month,
                                            1,
                                        ).toLocaleDateString(undefined, {
                                            year: 'numeric',
                                            month: 'long',
                                        })}
                                    </CardTitle>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        onClick={() => goToMonth(1)}
                                        aria-label="Next month"
                                    >
                                        <ChevronRight />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={goToToday}
                                        disabled={isCurrentMonth}
                                    >
                                        Today
                                    </Button>
                                </div>

                                <Select
                                    value={selectedVenue}
                                    onValueChange={setSelectedVenue}
                                >
                                    <SelectTrigger
                                        size="sm"
                                        aria-label="Filter by venue"
                                    >
                                        <SelectValue placeholder="All venues" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL_VENUES}>
                                            All venues
                                        </SelectItem>
                                        {venues.map((venue) => (
                                            <SelectItem
                                                key={venue}
                                                value={venue}
                                            >
                                                {venue}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </CardHeader>
                            <CardContent>
                                <VenueMonthGrid
                                    year={year}
                                    month={month}
                                    bookingsByDate={bookingsByDate}
                                    selectedDate={selectedDate}
                                    onSelectDay={handleSelectDay}
                                />

                                <div className="mt-3 flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                                    <span className="flex items-center gap-1.5">
                                        <span className="size-2.5 rounded-sm border border-success/40 bg-success/40" />
                                        Confirmed — blocks the venue
                                    </span>
                                    <span className="flex items-center gap-1.5">
                                        <span className="size-2.5 rounded-sm border border-dashed border-warning/60" />
                                        Tentative — warns only
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {selectedDate
                                        ? formatCalendarDate(selectedDate)
                                        : 'No day selected'}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {!selectedDate ? (
                                    <p className="text-sm text-muted-foreground">
                                        Select a day to see its bookings.
                                    </p>
                                ) : selectedDayBookings.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No bookings on this day.
                                    </p>
                                ) : (
                                    <div className="space-y-2">
                                        {selectedDayBookings.map((booking) => (
                                            <VenueBookingRow
                                                key={booking.id}
                                                booking={booking}
                                                showVenue={
                                                    selectedVenue === ALL_VENUES
                                                }
                                            />
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                ) : filteredActivities.length === 0 ? (
                    <Card>
                        <CardContent>
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <CalendarX2 />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        No activities on the calendar yet
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        {selectedVenue === ALL_VENUES
                                            ? 'Once an activity calendar or proposal is submitted, it’ll show up here.'
                                            : 'No bookings match the selected venue.'}
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        </CardContent>
                    </Card>
                ) : (
                    Object.keys(groupedByVenue)
                        .sort()
                        .map((venue) => (
                            <Card key={venue}>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {venue}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {Object.keys(groupedByVenue[venue])
                                        .sort()
                                        .map((date) => (
                                            <div key={date}>
                                                <p className="mb-2 text-sm font-medium text-muted-foreground">
                                                    {formatCalendarDate(date)}
                                                </p>
                                                <div className="space-y-2">
                                                    {groupedByVenue[venue][
                                                        date
                                                    ].map((booking) => (
                                                        <VenueBookingRow
                                                            key={booking.id}
                                                            booking={booking}
                                                        />
                                                    ))}
                                                </div>
                                            </div>
                                        ))}
                                </CardContent>
                            </Card>
                        ))
                )}
            </div>
        </>
    );
}

CalendarIndex.layout = {
    breadcrumbs: [{ title: 'Calendar' }],
};
