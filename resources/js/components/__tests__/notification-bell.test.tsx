import type * as InertiaReact from '@inertiajs/react';
import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { NotificationBell } from '@/components/notification-bell';
import type { NotificationItem, NotificationsProp } from '@/types/notifications';

/**
 * First test in the repo to mock usePage() (see the explore notes this
 * feature's plan left behind) — follows the same partial-mock-via-
 * importOriginal shape as manage-two-factor.test.tsx, adding usePage
 * alongside the router overrides so the component's real `notifications`
 * prop read is exercised, not stubbed away.
 *
 * markRead is deliberately NOT asserted via a mocked router.patch — the
 * component fires it as a plain `fetch()` (see notification-bell.tsx's
 * handleMarkRead docblock: it must not race router.visit(), which
 * router.patch would). `fetchMock` stands in for that.
 */
const { reloadMock, patchMock, visitMock, usePageMock, fetchMock } = vi.hoisted(() => ({
    // Synchronously resolves onFinish, like a real (fast, local) reload
    // would — without this the component's loading state never clears and
    // the skeleton rows stay rendered forever in tests.
    reloadMock: vi.fn((options?: { onFinish?: () => void }) => options?.onFinish?.()),
    patchMock: vi.fn(),
    visitMock: vi.fn(),
    usePageMock: vi.fn(),
    fetchMock: vi.fn(() => Promise.resolve(new Response(null, { status: 302 }))),
}));

vi.mock('@inertiajs/react', async (importOriginal) => {
    const actual = await importOriginal<typeof InertiaReact>();

    return {
        ...actual,
        usePage: usePageMock,
        router: { ...actual.router, reload: reloadMock, patch: patchMock, visit: visitMock },
    };
});

function makeItem(overrides: Partial<NotificationItem> = {}): NotificationItem {
    return {
        id: 'abc-123',
        kind: 'approver_hand_off',
        title: 'Action needed: Sample Document',
        body: 'Organization Registration • Computing Society',
        // Origin-relative, matching what the server now stores
        // (App\Support\DocumentUrls::pathForReviewer() /
        // HandleInertiaRequests::toRelativePath() normalize legacy rows) —
        // see notification-bell.tsx, which just forwards whatever `url` it's
        // given to router.visit() without caring whether it's relative.
        status: null,
        url: '/review/registrations/1',
        readAt: null,
        createdAt: new Date().toISOString(),
        ...overrides,
    };
}

function mockNotifications(notifications: NotificationsProp) {
    usePageMock.mockReturnValue({
        props: { notifications },
    } as unknown as ReturnType<typeof InertiaReact.usePage>);
}

/**
 * Simulates a fresh `notifications` prop landing (e.g. the page reload a
 * router.visit navigation triggers) by updating the usePage() mock and
 * forcing the already-rendered component to read it again — usePage is
 * otherwise static across a render in these tests.
 */
function updateNotifications(rerender: (ui: ReactElement) => void, notifications: NotificationsProp) {
    mockNotifications(notifications);
    rerender(<NotificationBell />);
}

describe('NotificationBell', () => {
    beforeEach(() => {
        reloadMock.mockClear();
        patchMock.mockClear();
        visitMock.mockClear();
        fetchMock.mockClear();
        vi.stubGlobal('fetch', fetchMock);
        document.cookie = 'XSRF-TOKEN=test-token';
    });

    it('shows an empty state and a disabled mark-all-read control when there are no notifications', async () => {
        mockNotifications({ unreadCount: 0, items: [] });
        const user = userEvent.setup();
        render(<NotificationBell />);

        await user.click(screen.getByRole('button', { name: 'Notifications' }));

        expect(screen.getByText("You're all caught up")).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Mark all as read' })).toBeDisabled();
    });

    it('renders no unread badge and a plain aria-label when the unread count is zero', () => {
        mockNotifications({ unreadCount: 0, items: [] });
        render(<NotificationBell />);

        expect(screen.getByRole('button', { name: 'Notifications' })).toBeInTheDocument();
    });

    it('clamps the unread badge at 99+', () => {
        mockNotifications({ unreadCount: 150, items: [] });
        render(<NotificationBell />);

        expect(screen.getByText('99+')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Notifications, 150 unread' })).toBeInTheDocument();
    });

    it('lists notifications and enables mark-all-read when unread notifications exist', async () => {
        mockNotifications({ unreadCount: 1, items: [makeItem()] });
        const user = userEvent.setup();
        render(<NotificationBell />);

        await user.click(screen.getByRole('button', { name: 'Notifications, 1 unread' }));

        expect(screen.getByText('Action needed: Sample Document')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Mark all as read' })).toBeEnabled();
    });

    it('fires exactly one async partial reload for notifications when opened', async () => {
        mockNotifications({ unreadCount: 0, items: [] });
        const user = userEvent.setup();
        render(<NotificationBell />);

        await user.click(screen.getByRole('button', { name: 'Notifications' }));

        expect(reloadMock).toHaveBeenCalledTimes(1);
        expect(reloadMock).toHaveBeenCalledWith(
            expect.objectContaining({ only: ['notifications'], async: true }),
        );
    });

    it('clicking a notification marks it read via a plain fetch (not router.patch) and navigates to its url', async () => {
        mockNotifications({ unreadCount: 1, items: [makeItem()] });
        const user = userEvent.setup();
        render(<NotificationBell />);

        await user.click(screen.getByRole('button', { name: 'Notifications, 1 unread' }));
        await user.click(screen.getByText('Action needed: Sample Document'));

        expect(fetchMock).toHaveBeenCalledWith(
            '/notifications/abc-123/read',
            expect.objectContaining({ method: 'PATCH' }),
        );
        // router.patch must NOT be used for this — it would compete with
        // router.visit below for Inertia's single in-flight visit slot.
        expect(patchMock).not.toHaveBeenCalled();
        expect(visitMock).toHaveBeenCalledWith('/review/registrations/1');
    });

    it('closes the dropdown when a notification row is clicked, before navigating', async () => {
        mockNotifications({ unreadCount: 1, items: [makeItem()] });
        const user = userEvent.setup();
        render(<NotificationBell />);

        await user.click(screen.getByRole('button', { name: 'Notifications, 1 unread' }));
        expect(screen.getByText('Action needed: Sample Document')).toBeInTheDocument();

        await user.click(screen.getByText('Action needed: Sample Document'));

        // Radix unmounts DropdownMenuContent's children when closed — the
        // row text (and the whole panel) must be gone, not just visually
        // hidden, otherwise the menu's modal layer still traps clicks on
        // the page underneath after navigation.
        expect(screen.queryByText('Action needed: Sample Document')).not.toBeInTheDocument();
    });

    it('does not re-mark an already-read notification when clicked, but still navigates', async () => {
        mockNotifications({ unreadCount: 0, items: [makeItem({ readAt: new Date().toISOString() })] });
        const user = userEvent.setup();
        render(<NotificationBell />);

        await user.click(screen.getByRole('button', { name: 'Notifications' }));
        await user.click(screen.getByText('Action needed: Sample Document'));

        expect(fetchMock).not.toHaveBeenCalled();
        expect(visitMock).toHaveBeenCalledWith('/review/registrations/1');
    });

    it('the per-row mark-as-read control marks read without navigating', async () => {
        mockNotifications({ unreadCount: 1, items: [makeItem()] });
        const user = userEvent.setup();
        render(<NotificationBell />);

        await user.click(screen.getByRole('button', { name: 'Notifications, 1 unread' }));
        await user.click(screen.getByRole('button', { name: 'Mark as read' }));

        expect(fetchMock).toHaveBeenCalledWith(
            '/notifications/abc-123/read',
            expect.objectContaining({ method: 'PATCH' }),
        );
        expect(visitMock).not.toHaveBeenCalled();
    });

    it('gives an unread row a bolder title, an unread dot, and an accent border, none of which an already-read row gets', async () => {
        mockNotifications({
            unreadCount: 1,
            items: [
                makeItem({ id: 'unread-1', title: 'Unread item' }),
                makeItem({ id: 'read-1', title: 'Read item', readAt: new Date().toISOString() }),
            ],
        });
        const user = userEvent.setup();
        render(<NotificationBell />);

        await user.click(screen.getByRole('button', { name: 'Notifications, 1 unread' }));

        const unreadTitle = screen.getByText('Unread item');
        const readTitle = screen.getByText('Read item');
        const unreadRow = unreadTitle.closest('li');
        const readRow = readTitle.closest('li');

        expect(unreadTitle).toHaveClass('font-semibold');
        expect(readTitle).toHaveClass('font-normal', 'text-muted-foreground');
        expect(unreadRow).toHaveClass('border-l-primary');
        expect(readRow).toHaveClass('border-l-transparent');
        // The unread dot marker only renders next to the unread row's title.
        expect(unreadRow?.querySelector('.bg-primary.rounded-full')).not.toBeNull();
        expect(readRow?.querySelector('.bg-primary.rounded-full')).toBeNull();
        // Only an unread row gets the manual "Mark as read" control.
        expect(screen.getAllByRole('button', { name: 'Mark as read' })).toHaveLength(1);
    });

    it('updates the row and the badge count optimistically the instant mark-as-read is clicked, before the request resolves', async () => {
        mockNotifications({ unreadCount: 1, items: [makeItem()] });
        const user = userEvent.setup();
        render(<NotificationBell />);

        // Keep the element reference itself — Radix's modal dropdown hides
        // the rest of the tree from the accessibility tree while open, which
        // makes a fresh role-based re-query of the trigger unreliable here.
        const trigger = screen.getByRole('button', { name: 'Notifications, 1 unread' });

        await user.click(trigger);
        await user.click(screen.getByRole('button', { name: 'Mark as read' }));

        // No reload/refetch drove this — fetchMock's response never resolved
        // synchronously, so this can only be the optimistic update.
        expect(screen.queryByRole('button', { name: 'Mark as read' })).not.toBeInTheDocument();
        expect(screen.getByText('Action needed: Sample Document')).toHaveClass('font-normal', 'text-muted-foreground');
        expect(trigger).toHaveAttribute('aria-label', 'Notifications');
    });

    it('does not double-subtract once a fresh reload confirms the server has caught up (row-click navigation race)', async () => {
        // Two unread items so a spurious extra -1 is visible instead of
        // masked by the Math.max(0, ...) floor.
        mockNotifications({
            unreadCount: 2,
            items: [makeItem({ id: 'a', title: 'Item A' }), makeItem({ id: 'b', title: 'Item B' })],
        });
        const user = userEvent.setup();
        const { rerender } = render(<NotificationBell />);

        // Keep the element reference itself — Radix's modal dropdown hides
        // the rest of the tree from the accessibility tree while open, which
        // makes a fresh role-based re-query of the trigger unreliable here.
        const trigger = screen.getByRole('button', { name: 'Notifications, 2 unread' });

        await user.click(trigger);
        await user.click(screen.getAllByRole('button', { name: 'Mark as read' })[0]);

        // Optimistic: A is masked client-side, server hasn't been asked yet.
        expect(trigger).toHaveAttribute('aria-label', 'Notifications, 1 unread');

        // A navigation's own page load (router.visit's re-evaluated shared
        // prop) lands, and by now the fire-and-forget markRead PATCH has
        // already committed server-side: the fresh payload already shows A
        // read and unreadCount already down to 1. Without pruning the
        // now-redundant optimistic entry for A, this would read as 0.
        updateNotifications(rerender, {
            unreadCount: 1,
            items: [
                makeItem({ id: 'a', title: 'Item A', readAt: new Date().toISOString() }),
                makeItem({ id: 'b', title: 'Item B' }),
            ],
        });

        expect(trigger).toHaveAttribute('aria-label', 'Notifications, 1 unread');
    });

    it('gives an unread row a tinted icon chip that a read row loses', async () => {
        mockNotifications({
            unreadCount: 1,
            items: [
                makeItem({ id: 'unread-1', title: 'Unread item' }),
                makeItem({ id: 'read-1', title: 'Read item', readAt: new Date().toISOString() }),
            ],
        });
        const user = userEvent.setup();
        render(<NotificationBell />);

        await user.click(screen.getByRole('button', { name: 'Notifications, 1 unread' }));

        const unreadRow = screen.getByText('Unread item').closest('li');
        const readRow = screen.getByText('Read item').closest('li');

        // approver_hand_off's chip color (see notificationVisual).
        expect(unreadRow?.querySelector('.bg-info\\/15')).not.toBeNull();
        expect(readRow?.querySelector('.bg-info\\/15')).toBeNull();
    });

    it('renders the icon this app already uses for each outcome — CircleCheck for approved, CircleX for rejected, Undo2 for returned', async () => {
        mockNotifications({
            unreadCount: 0,
            items: [
                makeItem({ id: 'a', kind: 'document_outcome', status: 'approved', title: 'Approved doc' }),
                makeItem({ id: 'b', kind: 'document_outcome', status: 'rejected', title: 'Rejected doc' }),
                makeItem({ id: 'c', kind: 'document_outcome', status: 'returned', title: 'Returned doc' }),
            ],
        });
        const user = userEvent.setup();
        render(<NotificationBell />);

        await user.click(screen.getByRole('button', { name: 'Notifications' }));

        // Each row's icon: right lucide glyph, right semantic color — the
        // exact pairing ApprovalActionsCard already uses for these outcomes.
        expect(
            screen.getByText('Approved doc').closest('li')?.querySelector('.lucide-circle-check.text-success'),
        ).not.toBeNull();
        expect(
            screen.getByText('Rejected doc').closest('li')?.querySelector('.lucide-circle-x.text-destructive'),
        ).not.toBeNull();
        expect(
            screen.getByText('Returned doc').closest('li')?.querySelector('.lucide-undo2.text-warning'),
        ).not.toBeNull();
    });

    it('lets a long title wrap onto two lines instead of hard-truncating to one', async () => {
        mockNotifications({
            unreadCount: 1,
            items: [
                makeItem({
                    title: 'Action needed: Organization Renewal — A Very Long Organization Name That Would Previously Be Cut Off Mid-Word',
                }),
            ],
        });
        const user = userEvent.setup();
        render(<NotificationBell />);

        await user.click(screen.getByRole('button', { name: 'Notifications, 1 unread' }));

        const title = screen.getByText(/Action needed: Organization Renewal/);
        expect(title).toHaveClass('line-clamp-2');
        expect(title).not.toHaveClass('truncate');
    });

    it('shows a "View all notifications" link pointing at the full page', async () => {
        mockNotifications({ unreadCount: 1, items: [makeItem()] });
        const user = userEvent.setup();
        render(<NotificationBell />);

        await user.click(screen.getByRole('button', { name: 'Notifications, 1 unread' }));

        // Not exercised via a real click here — Inertia's <Link> drives
        // navigation through @inertiajs/core's own Router singleton rather
        // than the mocked `router` export this file overrides, so clicking
        // it for real in this jsdom environment (no booted Inertia app,
        // no initial page context) throws inside library internals
        // unrelated to this component's own logic.
        const link = screen.getByRole('link', { name: /View all notifications/ });
        expect(link).toHaveAttribute('href', '/notifications');
    });

    /*
     * The bell used to rely solely on normal page navigation (plus a
     * one-shot reload on open) to refresh the `notifications` prop — a user
     * parked on one page never learned a new notification had arrived. It
     * now also polls in the background; these pin the constraints that make
     * that safe (async, paused while reading, paused while backgrounded).
     */
    describe('background polling', () => {
        beforeEach(() => {
            vi.useFakeTimers();
        });

        afterEach(() => {
            vi.useRealTimers();
        });

        it('polls for fresh notifications asynchronously on an interval, without the dropdown open', () => {
            mockNotifications({ unreadCount: 0, items: [] });
            render(<NotificationBell />);
            reloadMock.mockClear();

            act(() => {
                vi.advanceTimersByTime(45_000);
            });

            expect(reloadMock).toHaveBeenCalledWith(
                expect.objectContaining({ only: ['notifications'], async: true }),
            );
        });

        it('does not poll while the dropdown is open, so rows are never reshuffled under the cursor', async () => {
            vi.useRealTimers();
            mockNotifications({ unreadCount: 0, items: [] });
            const user = userEvent.setup();
            render(<NotificationBell />);

            await user.click(screen.getByRole('button', { name: 'Notifications' }));
            reloadMock.mockClear();

            vi.useFakeTimers();
            act(() => {
                vi.advanceTimersByTime(45_000);
            });

            expect(reloadMock).not.toHaveBeenCalled();
        });

        it('does not poll while the tab is hidden', () => {
            mockNotifications({ unreadCount: 0, items: [] });
            render(<NotificationBell />);
            reloadMock.mockClear();

            Object.defineProperty(document, 'visibilityState', { value: 'hidden', configurable: true });

            act(() => {
                vi.advanceTimersByTime(45_000);
            });

            expect(reloadMock).not.toHaveBeenCalled();

            Object.defineProperty(document, 'visibilityState', { value: 'visible', configurable: true });
        });

        it('refreshes once when the tab becomes visible again', () => {
            mockNotifications({ unreadCount: 0, items: [] });
            render(<NotificationBell />);
            reloadMock.mockClear();

            Object.defineProperty(document, 'visibilityState', { value: 'visible', configurable: true });
            document.dispatchEvent(new Event('visibilitychange'));

            expect(reloadMock).toHaveBeenCalledWith(
                expect.objectContaining({ only: ['notifications'], async: true }),
            );
        });
    });
});
