/**
 * Pure, dependency-free date helpers for the venue calendar's month grid.
 *
 * Every date is represented either as a `"YYYY-MM-DD"` string or as a plain
 * `{ year, month }` pair (month is 0-indexed, matching `Date`). Grid cells are
 * always built via `new Date(year, month, day)` — local-time construction,
 * never `new Date(isoString)` — for the same reason `formatCalendarDate` in
 * `@/lib/utils` avoids it: parsing an ISO date string treats it as UTC
 * midnight, which can roll the displayed day back by one in negative-UTC-offset
 * zones. Local construction has no such shift.
 */

export type MonthGridDay = {
    /** `"YYYY-MM-DD"`, used as the lookup key into a bookings-by-date map. */
    iso: string;
    /** Day-of-month number, e.g. 1-31. */
    day: number;
    /** Whether this cell falls within the displayed month (vs. a leading/trailing filler day). */
    inMonth: boolean;
    /** Whether this cell is today, per the caller-supplied "now". */
    isToday: boolean;
};

const pad = (n: number): string => String(n).padStart(2, '0');

/**
 * Formats a year/month(0-indexed)/day triple as `"YYYY-MM-DD"`.
 */
export function toISODate(year: number, month: number, day: number): string {
    // Deriving from a real Date normalizes any out-of-range day/month
    // (e.g. day 0, month 12) the same way the grid below relies on.
    const date = new Date(year, month, day);

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/**
 * Adds `delta` months to a `{ year, month }` pair (month 0-indexed),
 * normalizing the year on overflow/underflow. `addMonths(2026, 11, 1)` →
 * `{ year: 2027, month: 0 }`; `addMonths(2026, 0, -1)` → `{ year: 2025, month: 11 }`.
 */
export function addMonths(
    year: number,
    month: number,
    delta: number,
): { year: number; month: number } {
    const total = year * 12 + month + delta;

    return {
        year: Math.floor(total / 12),
        month: ((total % 12) + 12) % 12,
    };
}

/**
 * Builds a 6-week (42-day) grid for the given month, starting each week on
 * Sunday — the fixed 6-row layout keeps the grid's height stable as the user
 * navigates between months of different lengths.
 *
 * Leading/trailing cells belong to the adjacent months (`inMonth: false`) so
 * every week is a full 7 days; callers render those muted rather than
 * omitting them, matching how most calendar apps fill the grid.
 *
 * @param year - Full year, e.g. 2026.
 * @param month - 0-indexed month (0 = January).
 * @param today - `"YYYY-MM-DD"` to compare against for `isToday`; defaults to the real current date.
 */
export function buildMonthGrid(
    year: number,
    month: number,
    today?: string,
): MonthGridDay[] {
    const todayIso = today ?? todayISODate();
    const firstOfMonth = new Date(year, month, 1);
    const startOffset = firstOfMonth.getDay(); // 0 (Sun) - 6 (Sat)
    const gridStart = new Date(year, month, 1 - startOffset);

    return Array.from({ length: 42 }, (_, i) => {
        const cellDate = new Date(
            gridStart.getFullYear(),
            gridStart.getMonth(),
            gridStart.getDate() + i,
        );
        const iso = toISODate(
            cellDate.getFullYear(),
            cellDate.getMonth(),
            cellDate.getDate(),
        );

        return {
            iso,
            day: cellDate.getDate(),
            inMonth:
                cellDate.getMonth() === month &&
                cellDate.getFullYear() === year,
            isToday: iso === todayIso,
        };
    });
}

/**
 * `"YYYY-MM-DD"` for the real current date.
 */
export function todayISODate(): string {
    const now = new Date();

    return toISODate(now.getFullYear(), now.getMonth(), now.getDate());
}

/**
 * `{ year, month }` (0-indexed) for the real current date — the initial
 * month a freshly-opened calendar should display.
 */
export function currentYearMonth(): { year: number; month: number } {
    const now = new Date();

    return { year: now.getFullYear(), month: now.getMonth() };
}
