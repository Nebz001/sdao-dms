import type * as InertiaReact from '@inertiajs/react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { NotificationBell } from '@/components/notification-bell';
import type { NotificationItem, NotificationsProp } from '@/types/notifications';

/**
 * First test in the repo to mock usePage() (see the explore notes this
 * feature's plan left behind) — follows the same partial-mock-via-
 * importOriginal shape as manage-two-factor.test.tsx, adding usePage
 * alongside the router overrides so the component's real `notifications`
 * prop read is exercised, not stubbed away.
 */
const { reloadMock, patchMock, visitMock, usePageMock } = vi.hoisted(() => ({
    // Synchronously resolves onFinish, like a real (fast, local) reload
    // would — without this the component's loading state never clears and
    // the skeleton rows stay rendered forever in tests.
    reloadMock: vi.fn((options?: { onFinish?: () => void }) => options?.onFinish?.()),
    patchMock: vi.fn(),
    visitMock: vi.fn(),
    usePageMock: vi.fn(),
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

describe('NotificationBell', () => {
    beforeEach(() => {
        reloadMock.mockClear();
        patchMock.mockClear();
        visitMock.mockClear();
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

    it('clicking a notification marks it read and navigates to its url', async () => {
        mockNotifications({ unreadCount: 1, items: [makeItem()] });
        const user = userEvent.setup();
        render(<NotificationBell />);

        await user.click(screen.getByRole('button', { name: 'Notifications, 1 unread' }));
        await user.click(screen.getByText('Action needed: Sample Document'));

        expect(patchMock).toHaveBeenCalledWith(
            '/notifications/abc-123/read',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(visitMock).toHaveBeenCalledWith('/review/registrations/1');
    });

    it('does not re-mark an already-read notification when clicked, but still navigates', async () => {
        mockNotifications({ unreadCount: 0, items: [makeItem({ readAt: new Date().toISOString() })] });
        const user = userEvent.setup();
        render(<NotificationBell />);

        await user.click(screen.getByRole('button', { name: 'Notifications' }));
        await user.click(screen.getByText('Action needed: Sample Document'));

        expect(patchMock).not.toHaveBeenCalled();
        expect(visitMock).toHaveBeenCalledWith('/review/registrations/1');
    });

    it('the per-row mark-as-read control marks read without navigating', async () => {
        mockNotifications({ unreadCount: 1, items: [makeItem()] });
        const user = userEvent.setup();
        render(<NotificationBell />);

        await user.click(screen.getByRole('button', { name: 'Notifications, 1 unread' }));
        await user.click(screen.getByRole('button', { name: 'Mark as read' }));

        expect(patchMock).toHaveBeenCalledWith(
            '/notifications/abc-123/read',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(visitMock).not.toHaveBeenCalled();
    });
});
