/**
 * Shared shape for a single venue-calendar booking, used across the
 * calendar/index page, its month-grid and booking-row components. No
 * single one of those owns it — the page fetches it, the grid buckets it
 * by date, and the row renders it.
 */
export type VenueBooking = {
    id: number;
    name: string;
    venue: string;
    /** `"YYYY-MM-DD"`. */
    activity_date: string;
    /** `"HH:MM"` (or `"HH:MM:SS"`). */
    start_time: string;
    end_time: string;
    /** `"approved"` (Confirmed — hard-blocks the venue) or `"in_review"` (Tentative — warns only). */
    status: string;
    organization: string;
    document_id: number;
};
