import { Head, router } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useState } from 'react';
import { index as notificationsIndex, markAllRead } from '@/actions/App/Http/Controllers/NotificationController';
import { NotificationRow } from '@/components/notification-row';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Skeleton } from '@/components/ui/skeleton';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useNotificationRead } from '@/hooks/use-notification-read';
import type { NotificationsPage, NotificationStatusFilter } from '@/types/notifications';

type Props = {
    notifications: NotificationsPage;
    filters: { status: NotificationStatusFilter };
    unreadCount: number;
};

const STATUS_ALL: NotificationStatusFilter = 'all';

const EMPTY_COPY: Record<NotificationStatusFilter, { title: string; description: string }> = {
    all: {
        title: 'Nothing here yet',
        description: 'Approvals, hand-offs, and account updates will show up here.',
    },
    unread: {
        title: "You're all caught up",
        description: 'No unread notifications right now.',
    },
    read: {
        title: 'No read notifications yet',
        description: "Notifications you've read will show up here.",
    },
};

/**
 * The full, filterable destination behind the bell dropdown's "View all
 * notifications" link — NotificationBell's own `notifications` prop is a
 * tight, capped teaser (HandleInertiaRequests::notificationsFor(), ~8 rows);
 * this is where genuine browsing of a user's notification history happens.
 * Structured like ActivityLogIndex: same filter-bar shape, same
 * Skeleton/Empty/pagination-footer states — reused, not reinvented. Reuses
 * NotificationRow + useNotificationRead so mark-as-read and
 * navigate-to-document behave identically to the dropdown.
 */
export default function NotificationsIndex({ notifications, filters, unreadCount }: Props) {
    const [status, setStatus] = useState<NotificationStatusFilter>(filters.status);
    const [loading, setLoading] = useState(false);
    const { markRowRead, markManyRead, visitNotification, applyOptimistic } = useNotificationRead();

    const { items, staleIds } = applyOptimistic(notifications.data);
    const effectiveUnreadCount = Math.max(0, unreadCount - staleIds.size);

    function handleStatusChange(value: string) {
        if (!value) {
            return;
        }

        const nextStatus = value as NotificationStatusFilter;
        setStatus(nextStatus);
        setLoading(true);
        router.get(
            notificationsIndex().url,
            nextStatus === STATUS_ALL ? {} : { status: nextStatus },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['notifications', 'filters', 'unreadCount'],
                onFinish: () => setLoading(false),
            },
        );
    }

    function goToPage(url: string | null) {
        if (!url) {
            return;
        }

        setLoading(true);
        router.get(
            url,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['notifications', 'filters', 'unreadCount'],
                onFinish: () => setLoading(false),
            },
        );
    }

    function handleMarkAllRead() {
        markManyRead(items);
        router.patch(markAllRead.url(), {}, { preserveScroll: true });
    }

    const emptyCopy = EMPTY_COPY[status];

    return (
        <>
            <Head title="Notifications" />

            <div className="space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-balance">Notifications</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Approvals, hand-offs, and account updates for your account.
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={effectiveUnreadCount === 0}
                        onClick={handleMarkAllRead}
                    >
                        Mark all as read
                    </Button>
                </div>

                <Card>
                    <CardHeader className="flex-row flex-wrap items-center justify-between gap-4">
                        <CardTitle className="text-base">
                            {status === 'unread' ? 'Unread' : status === 'read' ? 'Read' : 'All'} notifications
                        </CardTitle>
                        <ToggleGroup
                            type="single"
                            value={status}
                            onValueChange={handleStatusChange}
                            variant="outline"
                            aria-label="Filter by read status"
                        >
                            <ToggleGroupItem value="all" aria-label="All notifications">
                                All
                            </ToggleGroupItem>
                            <ToggleGroupItem value="unread" aria-label="Unread notifications">
                                Unread
                            </ToggleGroupItem>
                            <ToggleGroupItem value="read" aria-label="Read notifications">
                                Read
                            </ToggleGroupItem>
                        </ToggleGroup>
                    </CardHeader>
                    <CardContent className="p-0">
                        {loading ? (
                            <div className="flex flex-col gap-3 p-4">
                                {Array.from({ length: 5 }).map((_, i) => (
                                    <Skeleton key={i} className="h-14 w-full" />
                                ))}
                            </div>
                        ) : items.length === 0 ? (
                            <Empty className="border-none p-10">
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <Bell />
                                    </EmptyMedia>
                                    <EmptyTitle>{emptyCopy.title}</EmptyTitle>
                                    <EmptyDescription>{emptyCopy.description}</EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <ul>
                                {items.map((item) => (
                                    <NotificationRow
                                        key={item.id}
                                        item={item}
                                        onRowClick={() => visitNotification(item)}
                                        onMarkRead={() => markRowRead(item)}
                                    />
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                {!loading && items.length > 0 && (
                    <Card>
                        <CardContent className="flex items-center justify-between gap-4">
                            <p className="text-sm text-muted-foreground">
                                Showing {notifications.meta.from}–{notifications.meta.to} of{' '}
                                {notifications.meta.total}
                            </p>
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={!notifications.links.prev}
                                    onClick={() => goToPage(notifications.links.prev)}
                                >
                                    Previous
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={!notifications.links.next}
                                    onClick={() => goToPage(notifications.links.next)}
                                >
                                    Next
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

NotificationsIndex.layout = {
    breadcrumbs: [{ title: 'Notifications' }],
};
