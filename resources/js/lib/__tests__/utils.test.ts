import { describe, expect, it } from 'vitest';
import { formatActivityTime, scaleToPercent } from '@/lib/utils';

describe('scaleToPercent', () => {
    it('returns 0 for a zero value', () => {
        expect(scaleToPercent(0, 10)).toBe(0);
    });

    it('returns 100 when the value equals the max', () => {
        expect(scaleToPercent(10, 10)).toBe(100);
    });

    it('guards against a non-positive max instead of dividing by zero', () => {
        expect(scaleToPercent(5, 0)).toBe(0);
        expect(scaleToPercent(0, 0)).toBe(0);
    });

    it('scales two groups against a shared max, not each group’s own peak', () => {
        // This is the actual bug the funnel chart had: scaling each group's
        // steps against that group's own max makes its tallest step always
        // render at 100%, so a group with 1 proposal and a group with 15
        // look visually identical. Against a shared max, they don't.
        const globalMax = Math.max(1, 15);

        expect(scaleToPercent(1, globalMax)).toBeCloseTo((1 / 15) * 100);
        expect(scaleToPercent(15, globalMax)).toBe(100);
    });
});

describe('formatActivityTime', () => {
    const now = new Date(2026, 9, 2, 15, 0, 0);
    const ago = (minutes: number) => new Date(now.getTime() - minutes * 60_000).toISOString();

    it.each([
        [0, 'Just now'],
        [14, '14 min ago'],
        [60, '1 hr ago'],
        [180, '3 hrs ago'],
    ])('reads %i minutes ago as "%s"', (minutes, expected) => {
        expect(formatActivityTime(ago(minutes), now)).toBe(expected);
    });

    it('says Yesterday for the previous calendar day, even when under 48 hours', () => {
        expect(formatActivityTime(new Date(2026, 9, 1, 8, 0, 0).toISOString(), now)).toBe('Yesterday');
    });

    it('counts days within a week, then falls back to a short date', () => {
        expect(formatActivityTime(new Date(2026, 8, 29, 9, 0, 0).toISOString(), now)).toBe('3 days ago');
        expect(formatActivityTime(new Date(2026, 8, 10, 9, 0, 0).toISOString(), now)).toMatch(/Sep/);
    });
});
