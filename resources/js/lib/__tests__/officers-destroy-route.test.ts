import { describe, expect, it } from 'vitest';
import * as officers from '@/routes/officers';

/**
 * Regression: Manage Officers once called officers.destroy(orgId, membershipId)
 * with two positional arguments. Wayfinder's multi-parameter routes take ONE
 * object (or tuple), so the URL builder received a bare number and threw
 * "Cannot read properties of undefined (reading 'toString')" before any
 * request was sent — the Deactivate button silently did nothing.
 */
describe('officers.destroy route', () => {
    it('builds the DELETE url from an object argument', () => {
        expect(officers.destroy({ organization: 7, membership: 42 })).toEqual({
            url: '/organizations/7/officers/42',
            method: 'delete',
        });
    });

    it('builds the same url from a tuple argument', () => {
        expect(officers.destroy([7, 42]).url).toBe(
            '/organizations/7/officers/42',
        );
    });

    // Pins Wayfinder's own argument handling (it happens to throw on a bare
    // number), not our code — it may need updating if Wayfinder changes how
    // it treats malformed arguments. The call-site guard is
    // components/__tests__/officers-index-page.test.tsx.
    it('throws when called with two positional arguments (the old, broken call shape)', () => {
        const positional = officers.destroy as unknown as (
            ...args: number[]
        ) => unknown;

        expect(() => positional(7, 42)).toThrow(TypeError);
    });
});
