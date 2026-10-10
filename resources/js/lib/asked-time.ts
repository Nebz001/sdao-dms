/**
 * "Asked today" / "Asked yesterday" / "Asked 3 days ago", counted in calendar
 * days in Asia/Manila (the campus's zone), whatever zone the viewer's browser
 * is set to. `now` is injectable for tests.
 */
const ZONE = 'Asia/Manila';

function manilaDay(date: Date): number {
    const [year, month, day] = date
        .toLocaleDateString('en-CA', { timeZone: ZONE })
        .split('-')
        .map(Number);

    return Date.UTC(year, month - 1, day);
}

export function askedLabel(createdAt: string, now: Date = new Date()): string {
    const days = Math.max(0, Math.round((manilaDay(now) - manilaDay(new Date(createdAt))) / 86_400_000));

    if (days === 0) {
        return 'Asked today';
    }

    return days === 1 ? 'Asked yesterday' : `Asked ${days} days ago`;
}

/** "Oct 7, 2026, 3:42 PM" in Asia/Manila, for the tooltip. */
export function askedExact(createdAt: string): string {
    return new Date(createdAt).toLocaleString('en-US', {
        timeZone: ZONE,
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}
