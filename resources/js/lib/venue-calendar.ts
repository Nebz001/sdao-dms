import type { ActivityScope } from '@/components/venue-calendar-filter';
import type { VenueBooking } from '@/types/venue-calendar';

export const ALL_VENUES = 'all';
export const COMING_UP_LIMIT = 10;

export type BookingFilter = {
    scope: ActivityScope;
    /** The viewer's own organization (RSO users), used by the "mine" scope. */
    ownOrganization: string | null;
    /** A single chosen organization (admin and other full-calendar roles). */
    organization: string | null;
    venue: string;
};

/**
 * The one place the who-filter and the venue filter are applied. The month
 * grid, the selected-day card, "Coming up" and the List view all start from
 * its result, so they can never disagree about what is visible.
 */
export function filterBookings(
    bookings: VenueBooking[],
    { scope, ownOrganization, organization, venue }: BookingFilter,
): VenueBooking[] {
    return bookings.filter((booking) => {
        if (scope === 'mine' && booking.organization !== ownOrganization) {
            return false;
        }

        if (organization !== null && booking.organization !== organization) {
            return false;
        }

        return venue === ALL_VENUES || booking.venue === venue;
    });
}

/** Buckets bookings by `"YYYY-MM-DD"` for the month grid and the selected-day card. */
export function groupByDate(
    bookings: VenueBooking[],
): Record<string, VenueBooking[]> {
    const map: Record<string, VenueBooking[]> = {};

    for (const booking of bookings) {
        (map[booking.activity_date] ??= []).push(booking);
    }

    return map;
}

export type ComingUp = {
    items: VenueBooking[];
    /** True when more upcoming bookings exist beyond `items` (the selected day not counted). */
    hasMore: boolean;
    /** Upcoming bookings hidden because they fall on the selected day. */
    hiddenOnSelectedDay: number;
};

/**
 * The next bookings from `today` onward, sorted by date then start time.
 * Bookings on the selected day are removed BEFORE the limit is applied, so
 * the list still fills up with other days; the selected-day card already
 * shows those.
 */
export function selectComingUp(
    filtered: VenueBooking[],
    {
        today,
        selectedDate,
        limit = COMING_UP_LIMIT,
    }: { today: string; selectedDate: string | null; limit?: number },
): ComingUp {
    const upcoming = filtered
        .filter((booking) => booking.activity_date >= today)
        .sort(
            (a, b) =>
                a.activity_date.localeCompare(b.activity_date) ||
                a.start_time.localeCompare(b.start_time),
        );
    const others = upcoming.filter(
        (booking) => booking.activity_date !== selectedDate,
    );

    return {
        items: others.slice(0, limit),
        hasMore: others.length > limit,
        hiddenOnSelectedDay: upcoming.length - others.length,
    };
}
