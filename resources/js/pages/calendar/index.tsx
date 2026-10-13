import { Head, router, usePage } from '@inertiajs/react';
import { CalendarX2, ChevronLeft, ChevronRight, FilterX } from 'lucide-react';
import { useMemo, useState } from 'react';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import VenueBookingRow from '@/components/venue-booking-row';
import {
    OrganizationFilter,
    ScopeToggle,
} from '@/components/venue-calendar-filter';
import type { ActivityScope } from '@/components/venue-calendar-filter';
import {
    CalendarSkeleton,
    ComingUpCard,
    SelectedDayCard,
    SideCardSkeleton,
} from '@/components/venue-calendar-side-cards';
import VenueMonthGrid from '@/components/venue-month-grid';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import {
    addMonths,
    currentYearMonth,
    todayISODate,
    toISODate,
} from '@/lib/month-grid';
import { formatCalendarDate } from '@/lib/utils';
import {
    ALL_VENUES,
    filterBookings,
    groupByDate,
    selectComingUp,
} from '@/lib/venue-calendar';
import { show as showActivityProposal } from '@/routes/activity-proposals';
import { show as reviewActivityProposal } from '@/routes/review/activity-proposals';
import type { VenueBooking } from '@/types/venue-calendar';

type Props = {
    /** Undefined only while the bookings are still loading. */
    activities?: VenueBooking[];
};

/**
 * Shared venue calendar — activities whose proposal has been fully approved. Defaults to a month grid with a selected-day card and a
 * "Coming up" card; the venue-grouped list stays available as a secondary
 * view. Read-only: this page displays existing bookings only.
 *
 * The activity filter (own organization / one organization / everything) is
 * display-only and lives in the query string (`?scope=mine` for officers,
 * `?org=<name>` for everyone else). Conflict checking happens on the server
 * against all bookings and never sees this filter.
 */
export default function CalendarIndex({ activities }: Props) {
    useDocumentUpdates(['activities']);

    const page = usePage();
    const auth = page.props.auth;
    const pageUrl = page.url;
    const isOfficer = auth?.isActiveOfficer ?? false;
    const ownOrganization = auth?.organization?.name ?? null;

    const query = new URLSearchParams(pageUrl.split('?')[1] ?? '');
    const scope: ActivityScope =
        isOfficer && query.get('scope') === 'mine' ? 'mine' : 'all';
    const organizationFilter = !isOfficer ? query.get('org') : null;

    const [view, setView] = useState<'month' | 'list'>('month');
    const [{ year, month }, setYearMonth] = useState(currentYearMonth());
    const [selectedVenue, setSelectedVenue] = useState<string>(ALL_VENUES);
    const [selectedDate, setSelectedDate] = useState<string>(todayISODate());

    const allActivities = useMemo(() => activities ?? [], [activities]);

    const organizations = useMemo(
        () =>
            Array.from(
                new Set(allActivities.map((a) => a.organization)),
            ).sort(),
        [allActivities],
    );

    const venues = useMemo(
        () => Array.from(new Set(allActivities.map((a) => a.venue))).sort(),
        [allActivities],
    );

    const filterActive = scope === 'mine' || organizationFilter !== null;

    // One filtered list feeds the grid, the selected-day card, Coming up and
    // the List view, so they always agree.
    const filteredActivities = useMemo(
        () =>
            filterBookings(allActivities, {
                scope,
                ownOrganization,
                organization: organizationFilter,
                venue: selectedVenue,
            }),
        [
            allActivities,
            scope,
            ownOrganization,
            organizationFilter,
            selectedVenue,
        ],
    );

    const bookingsByDate = useMemo(
        () => groupByDate(filteredActivities),
        [filteredActivities],
    );

    // List view: venue -> date -> bookings.
    const groupedByVenue = useMemo(() => {
        const grouped: Record<string, Record<string, VenueBooking[]>> = {};

        for (const activity of filteredActivities) {
            ((grouped[activity.venue] ??= {})[activity.activity_date] ??=
                []).push(activity);
        }

        return grouped;
    }, [filteredActivities]);

    const comingUp = useMemo(
        () =>
            selectComingUp(filteredActivities, {
                today: todayISODate(),
                selectedDate,
            }),
        [filteredActivities, selectedDate],
    );

    const monthPrefix = toISODate(year, month, 1).slice(0, 7);
    const monthHasBookings = filteredActivities.some((a) =>
        a.activity_date.startsWith(monthPrefix),
    );

    const selectedDayBookings = bookingsByDate[selectedDate] ?? [];

    function updateFilter(next: {
        scope?: ActivityScope;
        org?: string | null;
    }) {
        const [path, search = ''] = pageUrl.split('?');
        const params = new URLSearchParams(search);

        params.delete('scope');
        params.delete('org');

        if (next.scope === 'mine') {
            params.set('scope', 'mine');
        }

        if (next.org) {
            params.set('org', next.org);
        }

        const nextSearch = params.toString();

        router.replace({
            url: nextSearch ? `${path}?${nextSearch}` : path,
            preserveScroll: true,
            preserveState: true,
        });
    }

    function clearFilter() {
        updateFilter({});
    }

    function goToMonth(delta: number) {
        setYearMonth((prev) => addMonths(prev.year, prev.month, delta));
    }

    function goToToday() {
        setYearMonth(currentYearMonth());
        setSelectedDate(todayISODate());
    }

    function handleSelectDay(iso: string) {
        setSelectedDate(iso);

        const [y, m] = iso.split('-').map(Number);

        if (y !== year || m - 1 !== month) {
            setYearMonth({ year: y, month: m - 1 });
        }
    }

    // The calendar lists every activity, but the server says which documents
    // this user may open (can_view_proposal). Not allowed means no button at
    // all, never a disabled one or a link to the 403 page.
    function proposalHref(booking: VenueBooking): string | null {
        if (!booking.can_view_proposal) {
            return null;
        }

        return isOfficer
            ? showActivityProposal.url(booking.document_id)
            : reviewActivityProposal.url(booking.document_id);
    }

    const loading = activities === undefined;

    const venueSelect = (
        <Select value={selectedVenue} onValueChange={setSelectedVenue}>
            <SelectTrigger
                size="sm"
                aria-label="Filter by venue"
                className="min-w-36"
            >
                <SelectValue placeholder="All venues" />
            </SelectTrigger>
            <SelectContent>
                <SelectGroup>
                    <SelectItem value={ALL_VENUES}>All venues</SelectItem>
                    {venues.map((venue) => (
                        <SelectItem key={venue} value={venue}>
                            {venue}
                        </SelectItem>
                    ))}
                </SelectGroup>
            </SelectContent>
        </Select>
    );

    const activityFilter = isOfficer ? (
        <ScopeToggle
            value={scope}
            onChange={(next) => updateFilter({ scope: next })}
        />
    ) : (
        <OrganizationFilter
            organizations={organizations}
            value={organizationFilter}
            onChange={(next) => updateFilter({ org: next })}
        />
    );

    const noMatchEmpty = (
        <Empty>
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <FilterX />
                </EmptyMedia>
                <EmptyTitle>No bookings match this filter</EmptyTitle>
                <EmptyDescription>
                    Nothing is booked for this view with the current filters.
                </EmptyDescription>
            </EmptyHeader>
            <EmptyContent>
                <Button variant="outline" size="sm" onClick={clearFilter}>
                    Clear filter
                </Button>
            </EmptyContent>
        </Empty>
    );

    return (
        <>
            <Head title="Venue Calendar" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0 flex-1 basis-80">
                        <PageHeader
                            title="Venue Calendar"
                            subtitle="Venue bookings from fully approved activity proposals across all organizations. Venue names must be spelled the same way for conflicts to be caught."
                        />
                    </div>
                    <ToggleGroup
                        type="single"
                        value={view}
                        onValueChange={(value) =>
                            value && setView(value as 'month' | 'list')
                        }
                        className="gap-1 rounded-lg border bg-card p-1"
                        aria-label="Calendar view"
                    >
                        <ToggleGroupItem
                            value="month"
                            aria-label="Month view"
                            className="rounded-md! data-[state=on]:bg-accent"
                        >
                            Month
                        </ToggleGroupItem>
                        <ToggleGroupItem
                            value="list"
                            aria-label="List view"
                            className="rounded-md! data-[state=on]:bg-accent"
                        >
                            List
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>

                {view === 'month' ? (
                    <div className="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                        {loading ? (
                            <>
                                <CalendarSkeleton />
                                <div className="relative min-w-0">
                                    <div className="flex flex-col gap-6 xl:absolute xl:inset-0">
                                        <SideCardSkeleton
                                            rows={1}
                                            className="shrink-0"
                                        />
                                        <SideCardSkeleton
                                            rows={4}
                                            className="xl:flex-1"
                                        />
                                    </div>
                                </div>
                            </>
                        ) : (
                            <>
                                <Card>
                                    <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-x-4 gap-y-3">
                                        <div className="flex items-center gap-2">
                                            <Button
                                                variant="outline"
                                                size="icon"
                                                onClick={() => goToMonth(-1)}
                                                aria-label="Previous month"
                                            >
                                                <ChevronLeft />
                                            </Button>
                                            <CardTitle className="min-w-32 text-center text-lg sm:min-w-36">
                                                {new Date(
                                                    year,
                                                    month,
                                                    1,
                                                ).toLocaleDateString(
                                                    undefined,
                                                    {
                                                        year: 'numeric',
                                                        month: 'long',
                                                    },
                                                )}
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
                                                variant="outline"
                                                onClick={goToToday}
                                            >
                                                Today
                                            </Button>
                                        </div>

                                        <div className="flex flex-wrap items-center gap-2">
                                            {activityFilter}
                                            {venueSelect}
                                        </div>
                                    </CardHeader>
                                    <CardContent>
                                        {!monthHasBookings ? (
                                            filterActive ? (
                                                noMatchEmpty
                                            ) : (
                                                <Empty>
                                                    <EmptyHeader>
                                                        <EmptyMedia variant="icon">
                                                            <CalendarX2 />
                                                        </EmptyMedia>
                                                        <EmptyTitle>
                                                            No approved activities this month
                                                        </EmptyTitle>
                                                        <EmptyDescription>
                                                            Activities appear here once their proposal is fully approved. Use the arrows to look at another month.
                                                        </EmptyDescription>
                                                    </EmptyHeader>
                                                </Empty>
                                            )
                                        ) : (
                                            <VenueMonthGrid
                                                year={year}
                                                month={month}
                                                bookingsByDate={bookingsByDate}
                                                selectedDate={selectedDate}
                                                onSelectDay={handleSelectDay}
                                            />
                                        )}

                                    </CardContent>
                                </Card>

                                {/* On xl the content is absolutely positioned so it can never make the row taller than the calendar card. */}
                                <div className="relative min-w-0">
                                    <div className="flex flex-col gap-6 xl:absolute xl:inset-0">
                                        <SelectedDayCard
                                            selectedDate={selectedDate}
                                            bookings={selectedDayBookings}
                                            hrefFor={proposalHref}
                                        />
                                        <ComingUpCard
                                            comingUp={comingUp}
                                            selectedDate={selectedDate}
                                            onSelectDay={handleSelectDay}
                                            onViewAll={() => setView('list')}
                                        />
                                    </div>
                                </div>
                            </>
                        )}
                    </div>
                ) : (
                    <div className="flex flex-col gap-6">
                        <div className="flex flex-wrap items-center justify-end gap-2">
                            {activityFilter}
                            {venueSelect}
                        </div>

                        {filteredActivities.length === 0 ? (
                            <Card>
                                <CardContent>
                                    {filterActive ? (
                                        noMatchEmpty
                                    ) : (
                                        <Empty>
                                            <EmptyHeader>
                                                <EmptyMedia variant="icon">
                                                    <CalendarX2 />
                                                </EmptyMedia>
                                                <EmptyTitle>
                                                    No approved activities
                                                    yet
                                                </EmptyTitle>
                                                <EmptyDescription>
                                                    {selectedVenue ===
                                                    ALL_VENUES
                                                        ? 'An activity shows up here once its proposal has been fully approved.'
                                                        : 'No bookings match the selected venue.'}
                                                </EmptyDescription>
                                            </EmptyHeader>
                                        </Empty>
                                    )}
                                </CardContent>
                            </Card>
                        ) : (
                            Object.keys(groupedByVenue)
                                .sort()
                                .map((venue) => (
                                    <Card key={venue}>
                                        <CardHeader>
                                            <CardTitle className="text-lg">
                                                {venue}
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="flex flex-col gap-5">
                                            {Object.keys(groupedByVenue[venue])
                                                .sort()
                                                .map((date) => (
                                                    <div
                                                        key={date}
                                                        className="flex flex-col gap-2"
                                                    >
                                                        <p className="text-sm font-medium text-muted-foreground">
                                                            {formatCalendarDate(
                                                                date,
                                                            )}
                                                        </p>
                                                        {groupedByVenue[venue][
                                                            date
                                                        ].map((booking) => (
                                                            <VenueBookingRow
                                                                key={booking.id}
                                                                booking={
                                                                    booking
                                                                }
                                                            />
                                                        ))}
                                                    </div>
                                                ))}
                                        </CardContent>
                                    </Card>
                                ))
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

CalendarIndex.layout = {
    breadcrumbs: [{ title: 'Calendar' }],
};
