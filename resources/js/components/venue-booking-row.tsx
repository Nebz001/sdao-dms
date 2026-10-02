import { VenueStatusBadge } from '@/components/status-badge';
import { formatTimeRange } from '@/lib/utils';
import type { VenueBooking } from '@/types/venue-calendar';

type Props = {
    booking: VenueBooking;
    /** Show the venue name under the org/time line — needed where a list can span multiple venues. Omitted in the List view, where the venue is already the group heading. */
    showVenue?: boolean;
};

/**
 * A single booking as a compact bordered row: name, org (+ optionally venue),
 * time range, and a Confirmed/Tentative pill. Used by the List view; the
 * month view's day card uses the fuller VenueBookingCard.
 */
export default function VenueBookingRow({ booking, showVenue = false }: Props) {
    return (
        <div className="flex items-start justify-between gap-3 rounded-lg border bg-card px-4 py-3 transition-colors hover:bg-accent/50">
            <div className="min-w-0">
                <p className="text-sm font-semibold text-balance">
                    {booking.name}
                </p>
                <p className="mt-0.5 text-xs text-muted-foreground">
                    {booking.organization} ·{' '}
                    {formatTimeRange(booking.start_time, booking.end_time)}
                </p>
                {showVenue && (
                    <p className="text-xs text-muted-foreground">
                        {booking.venue}
                    </p>
                )}
            </div>
            <VenueStatusBadge
                status={
                    booking.status === 'approved' ? 'confirmed' : 'tentative'
                }
                className="shrink-0"
            />
        </div>
    );
}
