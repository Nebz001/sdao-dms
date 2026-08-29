import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useRelativeTime } from '@/hooks/use-relative-time';

/*
 * formatRelativeTime() (lib/utils.ts) is a pure, one-shot computation — a
 * component calling it directly during render freezes at whatever it said
 * on that render (e.g. "just now" forever) since nothing else re-renders the
 * row afterward. useRelativeTime wraps it so the displayed string actually
 * advances while a user sits on the page.
 */
describe('useRelativeTime', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('advances from "just now" to "1m ago" as time passes, without remounting', () => {
        vi.setSystemTime(new Date('2026-01-01T00:00:00Z'));
        const dateString = new Date('2026-01-01T00:00:00Z').toISOString();

        const { result } = renderHook(() => useRelativeTime(dateString));

        expect(result.current).toBe('just now');

        // advanceTimersByTime moves the faked Date.now() along with the
        // timer queue — no separate vi.setSystemTime call needed here.
        act(() => {
            vi.advanceTimersByTime(60_000);
        });

        expect(result.current).toBe('1m ago');
    });

    it('ticks again after an hour has passed, moving from minutes to hours', () => {
        vi.setSystemTime(new Date('2026-01-01T00:00:00Z'));
        const dateString = new Date('2026-01-01T00:00:00Z').toISOString();

        const { result } = renderHook(() => useRelativeTime(dateString));

        act(() => {
            vi.advanceTimersByTime(60 * 60_000);
        });

        expect(result.current).toBe('1h ago');
    });

    it('stops scheduling further ticks once past the 30-day cutover to a fixed date', () => {
        const thirtyOneDaysAgo = new Date(Date.now() - 31 * 24 * 60 * 60_000).toISOString();

        renderHook(() => useRelativeTime(thirtyOneDaysAgo));

        // Only a genuinely pending timer would throw off this count — if the
        // hook kept re-scheduling past the cutover (where the displayed
        // value can never change again), this assertion would fail.
        expect(vi.getTimerCount()).toBe(0);
    });

    it('recomputes immediately when dateString itself changes', () => {
        vi.setSystemTime(new Date('2026-01-01T00:00:00Z'));

        const { result, rerender } = renderHook(({ dateString }) => useRelativeTime(dateString), {
            initialProps: { dateString: new Date('2026-01-01T00:00:00Z').toISOString() },
        });

        expect(result.current).toBe('just now');

        vi.setSystemTime(new Date('2026-01-01T02:00:00Z'));
        rerender({ dateString: new Date('2026-01-01T01:59:00Z').toISOString() });

        expect(result.current).toBe('1m ago');
    });
});
