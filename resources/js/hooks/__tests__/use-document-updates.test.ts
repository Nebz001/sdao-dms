import { renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useDocumentUpdates } from '@/hooks/use-document-updates';

const reload = vi.fn();

vi.mock('@inertiajs/react', () => ({ router: { reload: (options: unknown) => reload(options) } }));

type ReloadOptions = {
    only: string[];
    async: boolean;
    onHttpException: (response: { status: number }) => boolean | void;
    onNetworkError: () => boolean | void;
    onFinish: () => void;
    onSuccess: () => void;
};

const lastOptions = () => reload.mock.calls.at(-1)![0] as ReloadOptions;

describe('useDocumentUpdates', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        reload.mockReset();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('polls every 5 seconds with an async partial reload', () => {
        renderHook(() => useDocumentUpdates(['document', 'view']));

        vi.advanceTimersByTime(5000);

        expect(reload).toHaveBeenCalledTimes(1);
        expect(lastOptions()).toMatchObject({ only: ['document', 'view'], async: true });
    });

    it('never overlaps polls: skips ticks while one is still in flight', () => {
        renderHook(() => useDocumentUpdates());

        vi.advanceTimersByTime(15000);
        expect(reload).toHaveBeenCalledTimes(1);

        lastOptions().onFinish();
        vi.advanceTimersByTime(5000);
        expect(reload).toHaveBeenCalledTimes(2);
    });

    it('swallows a 5xx so Inertia does not open its error dialog, and backs off', () => {
        renderHook(() => useDocumentUpdates());

        vi.advanceTimersByTime(5000);
        expect(lastOptions().onHttpException({ status: 502 })).toBe(false);
        lastOptions().onFinish();

        // Backed off: the next 5s tick is skipped, a later one goes through.
        vi.advanceTimersByTime(5000);
        expect(reload).toHaveBeenCalledTimes(1);
        vi.advanceTimersByTime(10000);
        expect(reload).toHaveBeenCalledTimes(2);
    });

    it('leaves 4xx responses to Inertia and resets the cadence after a success', () => {
        renderHook(() => useDocumentUpdates());

        vi.advanceTimersByTime(5000);
        expect(lastOptions().onHttpException({ status: 403 })).toBeUndefined();
        lastOptions().onFinish();

        vi.advanceTimersByTime(5000);
        expect(reload).toHaveBeenCalledTimes(2);
        lastOptions().onSuccess();
        lastOptions().onFinish();

        vi.advanceTimersByTime(5000);
        expect(reload).toHaveBeenCalledTimes(3);
    });

    it('does not poll while the tab is hidden', () => {
        const hidden = vi.spyOn(document, 'hidden', 'get').mockReturnValue(true);
        renderHook(() => useDocumentUpdates());

        vi.advanceTimersByTime(20000);
        expect(reload).not.toHaveBeenCalled();

        hidden.mockRestore();
    });
});
