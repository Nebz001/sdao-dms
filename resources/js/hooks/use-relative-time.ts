import { useEffect, useReducer } from 'react';
import { formatRelativeTime } from '@/lib/utils';

const THIRTY_SECONDS_MS = 30_000;
const HOUR_MS = 60 * 60_000;
const THIRTY_DAYS_MS = 30 * 24 * HOUR_MS;

/**
 * Ticking wrapper around formatRelativeTime(). formatRelativeTime() itself is
 * a pure, one-shot computation — a row calling it directly during render
 * freezes at whatever it said on that render (e.g. "just now" forever) and
 * never advances to "5m ago" as time actually passes, since nothing else
 * re-renders that row. The label itself stays a plain derived value computed
 * fresh from `dateString` on every render (never stored in state); the
 * effect below only forces a re-render on an interval sized to how fast that
 * derived value can change, and stops re-scheduling once formatRelativeTime()
 * is past its own 30-day cutover to a fixed absolute date, which can never
 * change again.
 */
export function useRelativeTime(dateString: string): string {
    const [, forceTick] = useReducer((count: number) => count + 1, 0);

    useEffect(() => {
        const date = new Date(dateString);
        let timer: ReturnType<typeof setTimeout> | undefined;

        function scheduleNext() {
            const age = Date.now() - date.getTime();

            if (age >= THIRTY_DAYS_MS) {
                return;
            }

            const delay = age < HOUR_MS ? THIRTY_SECONDS_MS : HOUR_MS;

            timer = setTimeout(() => {
                forceTick();
                scheduleNext();
            }, delay);
        }

        scheduleNext();

        return () => {
            if (timer) {
                clearTimeout(timer);
            }
        };
    }, [dateString]);

    return formatRelativeTime(dateString);
}
