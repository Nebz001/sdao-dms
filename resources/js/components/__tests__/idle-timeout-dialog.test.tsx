import type * as InertiaReact from '@inertiajs/react';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import IdleTimeoutDialog from '@/components/idle-timeout-dialog';

/**
 * Group E backlog — the idle-timeout warning is driven entirely by a
 * client-side "real interaction" clock (see use-idle-timeout.ts's docblock
 * for why background polling can't be trusted for this), so these specs
 * exercise it with fake timers rather than waiting out the real 15-minute
 * window. Mirrors notification-bell.test.tsx's usePage/router mocking shape.
 */
const { postMock, usePageMock } = vi.hoisted(() => ({
    postMock: vi.fn(),
    usePageMock: vi.fn(),
}));

vi.mock('@inertiajs/react', async (importOriginal) => {
    const actual = await importOriginal<typeof InertiaReact>();

    return {
        ...actual,
        usePage: usePageMock,
        router: { ...actual.router, post: postMock, flushAll: vi.fn() },
    };
});

const IDLE_MS = 15 * 60 * 1000;
const WARNING_MS = 60 * 1000;

function mockAuthedUser() {
    usePageMock.mockReturnValue({
        props: { auth: { user: { id: 1, name: 'Test User' } } },
    } as unknown as ReturnType<typeof InertiaReact.usePage>);
}

describe('IdleTimeoutDialog', () => {
    beforeEach(() => {
        postMock.mockClear();
        mockAuthedUser();
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('shows nothing while the user is within the idle window', () => {
        render(<IdleTimeoutDialog />);

        act(() => {
            vi.advanceTimersByTime(IDLE_MS - 1000);
        });

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('shows the warning once idle for the configured window, then logs out once the warning window also elapses', () => {
        render(<IdleTimeoutDialog />);

        act(() => {
            vi.advanceTimersByTime(IDLE_MS);
        });

        expect(screen.getByRole('dialog')).toBeInTheDocument();
        expect(screen.getByText('Still there?')).toBeInTheDocument();
        expect(postMock).not.toHaveBeenCalled();

        act(() => {
            vi.advanceTimersByTime(WARNING_MS);
        });

        expect(postMock).toHaveBeenCalledTimes(1);
        expect(postMock).toHaveBeenCalledWith(
            expect.stringContaining('/logout'),
            {},
            expect.any(Object),
        );
    });

    it('any tracked activity before the idle threshold resets the clock, so the warning never appears', () => {
        render(<IdleTimeoutDialog />);

        act(() => {
            vi.advanceTimersByTime(IDLE_MS - 60_000);
        });
        act(() => {
            window.dispatchEvent(new Event('mousemove'));
        });
        // Without the reset, this would cross the original 15-minute mark.
        act(() => {
            vi.advanceTimersByTime(120_000);
        });

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('"Stay signed in" dismisses the warning and resets the idle clock instead of logging out', () => {
        render(<IdleTimeoutDialog />);

        act(() => {
            vi.advanceTimersByTime(IDLE_MS);
        });
        expect(screen.getByRole('dialog')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Stay signed in' }));

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();

        act(() => {
            vi.advanceTimersByTime(WARNING_MS);
        });

        expect(postMock).not.toHaveBeenCalled();
    });

    it('"Log out now" logs out immediately without waiting for the warning countdown', () => {
        render(<IdleTimeoutDialog />);

        act(() => {
            vi.advanceTimersByTime(IDLE_MS);
        });

        fireEvent.click(screen.getByRole('button', { name: 'Log out now' }));

        expect(postMock).toHaveBeenCalledTimes(1);
    });
});
