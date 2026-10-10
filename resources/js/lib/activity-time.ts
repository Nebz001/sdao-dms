/**
 * Activity dates and times arrive as plain wall-clock strings ("2026-10-15",
 * "13:00") that already mean Asia/Manila time: the app stores no zone and the
 * server never converts them. They are formatted from their parts, never
 * through `new Date("2026-10-15")` (which reads as UTC and can land on the
 * day before), so the same text shows in every viewer's browser zone.
 */

/** "13:00" or "13:00:00" -> "1:00 PM" */
export function formatClock(time: string): string {
    const [hours, minutes] = time.split(':').map(Number);
    const suffix = hours >= 12 ? 'PM' : 'AM';
    const hour12 = hours % 12 === 0 ? 12 : hours % 12;

    return `${hour12}:${String(minutes).padStart(2, '0')} ${suffix}`;
}

/** "8:00 AM to 5:00 PM", or just the start when there is no end. */
export function formatClockRange(
    start: string | null | undefined,
    end: string | null | undefined,
): string | null {
    if (!start) {
        return null;
    }

    return end ? `${formatClock(start)} to ${formatClock(end)}` : formatClock(start);
}

const MONTHS = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

/** "2026-10-15" -> { month: "OCT", day: "15" } */
export function dateBadgeParts(date: string): { month: string; day: string } {
    const [, month, day] = date.split('-').map(Number);

    return { month: MONTHS[month - 1] ?? '', day: String(day) };
}

/** "2026-10-15" -> "Oct 15, 2026" */
export function formatActivityDate(date: string): string {
    const [year, month, day] = date.split('-').map(Number);
    const name = MONTHS[month - 1] ?? '';

    return `${name.charAt(0)}${name.slice(1).toLowerCase()} ${day}, ${year}`;
}
