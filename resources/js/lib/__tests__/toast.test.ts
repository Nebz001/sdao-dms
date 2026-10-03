import { afterEach, describe, expect, it } from 'vitest';
import {
    MAX_VISIBLE_TOASTS,
    dismissToast,
    getToasts,
    notify,
} from '@/lib/toast';

describe('toast store', () => {
    afterEach(() => {
        // Drain whatever a test left behind (queued toasts are promoted as
        // visible ones are dismissed, so loop until the screen is empty).
        while (getToasts().length > 0) {
            dismissToast(getToasts()[0].id);
        }
    });

    it('puts the newest toast on top', () => {
        const first = notify.success({ title: 'First', message: 'one' });
        const second = notify.info({ title: 'Second', message: 'two' });

        expect(getToasts().map((t) => t.id)).toEqual([second, first]);
    });

    it('shows at most three at a time and queues the rest', () => {
        for (let i = 0; i < MAX_VISIBLE_TOASTS + 2; i++) {
            notify.error({ title: `T${i}`, message: 'm' });
        }

        expect(getToasts()).toHaveLength(MAX_VISIBLE_TOASTS);
        expect(getToasts().map((t) => t.title)).toEqual(['T2', 'T1', 'T0']);

        dismissToast(getToasts()[0].id);

        // A queued toast takes the freed slot, again as the newest on top.
        expect(getToasts()).toHaveLength(MAX_VISIBLE_TOASTS);
        expect(getToasts()[0].title).toBe('T3');
    });

    it('auto closes success and info after 5s but keeps errors', () => {
        notify.success({ title: 'a', message: 'a' });
        notify.info({ title: 'b', message: 'b' });
        notify.error({ title: 'd', message: 'd' });

        const durations = Object.fromEntries(
            getToasts().map((t) => [t.kind, t.duration]),
        );

        expect(durations).toEqual({
            success: 5000,
            info: 5000,
            error: null,
        });
    });
});
