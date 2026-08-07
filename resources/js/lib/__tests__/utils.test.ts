import { describe, expect, it } from 'vitest';
import { scaleToPercent } from '@/lib/utils';

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
