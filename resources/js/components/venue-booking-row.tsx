import { Badge } from '@/components/ui/badge';
import { formatTimeRange } from '@/lib/utils';
import type { VenueBooking } from '@/types/venue-calendar';

type Props = {
    booking: VenueBooking;
    /** Show the venue name under the org/time line — needed in the day-detail panel, where a day can span multiple venues. Omitted in the List view, where the venue is already the group heading. */
    showVenue?: boolean;
};

/**
 * A single booking as a compact row: name, org (+ optionally venue), time
 * range, and a Confirmed/Tentative badge. Shared by the month grid's
 * day-detail panel and the List view so the two can't drift apart.
 */
export default function VenueBookingRow({ booking, showVenue = false }: Props) {
    const isConfirmed = booking.status === 'approved';

    return (
        <div className="flex items-center justify-between gap-3 rounded-md border px-3 py-2">
            <div className="min-w-0">
                <p className="truncate text-sm font-medium">{booking.name}</p>
                <p className="truncate text-xs text-muted-foreground">
                    {booking.organization} ·{' '}
                    {formatTimeRange(booking.start_time, booking.end_time)}
                </p>
                {showVenue && (
                    <p className="truncate text-xs text-muted-foreground">
                        {booking.venue}
                    </p>
                )}
            </div>
            <Badge
                variant="outline"
                className={
                    isConfirmed
                        ? 'shrink-0 border-success/20 bg-success/10 text-success-foreground'
                        : 'shrink-0 border-warning/20 bg-warning/10 text-warning-foreground'
                }
            >
                {isConfirmed ? 'Confirmed' : 'Tentative'}
            </Badge>
        </div>
    );
}
