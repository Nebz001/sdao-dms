import { afterEach, describe, expect, it } from 'vitest';
import { addMonths, buildMonthGrid, toISODate } from '@/lib/month-grid';

describe('toISODate', () => {
    it('zero-pads month and day', () => {
        expect(toISODate(2026, 0, 5)).toBe('2026-01-05');
        expect(toISODate(2026, 11, 31)).toBe('2026-12-31');
    });
});

describe('addMonths', () => {
    it('rolls the year forward past December', () => {
        expect(addMonths(2026, 11, 1)).toEqual({ year: 2027, month: 0 });
    });

    it('rolls the year backward past January', () => {
        expect(addMonths(2026, 0, -1)).toEqual({ year: 2025, month: 11 });
    });

    it('stays within the same year for an interior move', () => {
        expect(addMonths(2026, 5, 2)).toEqual({ year: 2026, month: 7 });
    });
});

describe('buildMonthGrid', () => {
    it('always returns 6 full weeks (42 days)', () => {
        expect(buildMonthGrid(2026, 8)).toHaveLength(42);
    });

    it('fills leading days from the previous month when the 1st is mid-week', () => {
        // September 2026 opens on a Tuesday.
        const grid = buildMonthGrid(2026, 8);

        expect(grid[0]).toMatchObject({
            iso: '2026-08-30',
            day: 30,
            inMonth: false,
        });
        expect(grid[1]).toMatchObject({
            iso: '2026-08-31',
            day: 31,
            inMonth: false,
        });
        expect(grid[2]).toMatchObject({
            iso: '2026-09-01',
            day: 1,
            inMonth: true,
        });
    });

    it('fills leading days from the previous month when the 1st is a Saturday', () => {
        // August 2026 opens on a Saturday — 6 leading filler days.
        const grid = buildMonthGrid(2026, 7);

        expect(grid[0]).toMatchObject({ iso: '2026-07-26', inMonth: false });
        expect(grid[5]).toMatchObject({ iso: '2026-07-31', inMonth: false });
        expect(grid[6]).toMatchObject({
            iso: '2026-08-01',
            day: 1,
            inMonth: true,
        });
    });

    it('has no leading filler when the month opens on Sunday', () => {
        // February 2026 opens on a Sunday.
        const grid = buildMonthGrid(2026, 1);

        expect(grid[0]).toMatchObject({
            iso: '2026-02-01',
            day: 1,
            inMonth: true,
        });
    });

    it('includes trailing filler days from the next month', () => {
        const grid = buildMonthGrid(2026, 8);
        const last = grid[41];

        expect(last.inMonth).toBe(false);
        expect(last.iso.startsWith('2026-10')).toBe(true);
    });

    it('includes Feb 29 in a leap year, marked in-month', () => {
        const grid = buildMonthGrid(2024, 1);
        const leapDay = grid.find((d) => d.iso === '2024-02-29');

        expect(leapDay).toMatchObject({ day: 29, inMonth: true });
    });

    it('excludes Feb 29 in a non-leap year', () => {
        const grid = buildMonthGrid(2026, 1);

        expect(grid.some((d) => d.iso === '2026-02-29')).toBe(false);
    });

    it('marks exactly the day matching the supplied "today"', () => {
        const grid = buildMonthGrid(2026, 8, '2026-09-15');
        const todayCells = grid.filter((d) => d.isToday);

        expect(todayCells).toHaveLength(1);
        expect(todayCells[0].iso).toBe('2026-09-15');
    });

    it('marks no day as today when "today" falls outside the displayed grid entirely', () => {
        const grid = buildMonthGrid(2026, 8, '2026-11-15');

        expect(grid.every((d) => !d.isToday)).toBe(true);
    });
});

describe('buildMonthGrid — timezone independence', () => {
    const originalTZ = process.env.TZ;

    afterEach(() => {
        process.env.TZ = originalTZ;
    });

    it('places the 1st of the month in its correct cell under a negative UTC-offset zone', () => {
        // The bug this guards against: parsing a "YYYY-MM-DD" string with
        // `new Date(isoString)` reads it as UTC midnight, which a
        // negative-offset zone then displays as the last day of the
        // *previous* month. buildMonthGrid never does that — it only ever
        // constructs Dates from (year, month, day) parts — so this must hold
        // regardless of the runtime's local timezone.
        process.env.TZ = 'America/Los_Angeles';

        const grid = buildMonthGrid(2026, 8);
        const first = grid.find((d) => d.inMonth && d.day === 1);

        expect(first?.iso).toBe('2026-09-01');
    });

    it('places the 1st of the month in its correct cell under a positive UTC-offset zone', () => {
        process.env.TZ = 'Asia/Manila';

        const grid = buildMonthGrid(2026, 8);
        const first = grid.find((d) => d.inMonth && d.day === 1);

        expect(first?.iso).toBe('2026-09-01');
    });
});
