/**
 * Shape of a single activity in the public landing page's activities widget
 * — deliberately narrower than `VenueBooking` (@/types/venue-calendar).
 * Every row here is Approved by construction (see
 * `App\Http\Controllers\HomeController::approvedActivities()`), so there is
 * no `status` field to carry, and `document_id` is an internal identifier
 * with no public use. `description` is dropped entirely — the public row
 * shows `venue` instead.
 */
export type PublicActivity = {
    id: number;
    name: string;
    venue: string;
    /** `"YYYY-MM-DD"`. */
    activity_date: string;
    /** `"HH:MM"`, or null. */
    start_time: string | null;
    end_time: string | null;
    organization: string;
};
