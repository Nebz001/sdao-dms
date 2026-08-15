import { Link, router, usePage } from '@inertiajs/react';
import { Bell, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { index as notificationsIndex, markAllRead } from '@/actions/App/Http/Controllers/NotificationController';
import { NotificationRow } from '@/components/notification-row';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Skeleton } from '@/components/ui/skeleton';
import { useNotificationRead } from '@/hooks/use-notification-read';

/**
 * The bell's data is deliberately NOT kept fresh by a background poll — see
 * use-document-updates.ts for the pattern this intentionally does not reuse.
 * Freshness instead comes from two cheap sources: the shared `notifications`
 * prop (HandleInertiaRequests::share()) refreshing on every normal page
 * navigation, and a one-shot partial reload fired right here when the panel
 * is actually opened, so what's shown is never more than one click stale.
 *
 * The prop itself is a tight, unread-first, ~8-row teaser
 * (HandleInertiaRequests::notificationsFor()) — the full, paginated,
 * filterable history lives at /notifications (NotificationController::index,
 * "View all notifications" below), same capped-teaser-vs-full-page split as
 * the dashboard's "Recent Activity" card vs. the Activity Log page.
 */
export function NotificationBell() {
    const { notifications } = usePage().props;
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);
    const { markRowRead, markManyRead, visitNotification, resetOptimisticReadIds, applyOptimistic } = useNotificationRead();

    const { items, staleIds } = applyOptimistic(notifications?.items ?? []);
    const unreadCount = Math.max(0, (notifications?.unreadCount ?? 0) - staleIds.size);

    function handleOpenChange(nextOpen: boolean) {
        setOpen(nextOpen);

        if (!nextOpen) {
            return;
        }

        setLoading(true);
        router.reload({
            only: ['notifications'],
            async: true,
            onFinish: () => {
                setLoading(false);
                resetOptimisticReadIds();
            },
        });
    }

    function handleRowClick(item: (typeof items)[number]) {
        // Close explicitly rather than relying on Radix's own dismiss
        // handling: the row is a plain <button>, not a DropdownMenuItem, so
        // no built-in close-on-select fires for it, and DropdownMenu's
        // default modal={true} leaves `pointer-events: none` on <body>
        // until it does — which would otherwise strand the page unusable
        // even after a successful navigation.
        visitNotification(item, () => setOpen(false));
    }

    function handleMarkAllRead() {
        markManyRead(items);
        router.patch(markAllRead.url(), {}, { preserveScroll: true });
    }

    return (
        <DropdownMenu open={open} onOpenChange={handleOpenChange}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label={unreadCount > 0 ? `Notifications, ${unreadCount} unread` : 'Notifications'}
                >
                    <Bell />
                    {unreadCount > 0 && (
                        <Badge
                            variant="destructive"
                            className="absolute -top-1 -right-1 h-4 min-w-4 justify-center rounded-full px-1 text-[10px] leading-none"
                        >
                            {unreadCount > 99 ? '99+' : unreadCount}
                        </Badge>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-96 p-0">
                <div className="flex items-center justify-between gap-2 border-b px-3 py-2">
                    <span className="text-sm font-medium">Notifications</span>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-auto px-2 py-1 text-xs"
                        disabled={unreadCount === 0}
                        onClick={handleMarkAllRead}
                    >
                        Mark all as read
                    </Button>
                </div>

                {loading ? (
                    <div className="flex flex-col gap-3 p-3">
                        <Skeleton className="h-12 w-full" />
                        <Skeleton className="h-12 w-full" />
                        <Skeleton className="h-12 w-full" />
                    </div>
                ) : items.length === 0 ? (
                    <Empty className="border-none p-6">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Bell />
                            </EmptyMedia>
                            <EmptyTitle>You&apos;re all caught up</EmptyTitle>
                            <EmptyDescription>
                                Approvals, hand-offs, and account updates will show up here.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <ul className="max-h-[420px] overflow-y-auto py-1">
                        {items.map((item) => (
                            <NotificationRow
                                key={item.id}
                                item={item}
                                onRowClick={() => handleRowClick(item)}
                                onMarkRead={() => markRowRead(item)}
                            />
                        ))}
                    </ul>
                )}

                <Link
                    href={notificationsIndex()}
                    onClick={() => setOpen(false)}
                    className="flex items-center justify-center gap-1 border-t px-3 py-2 text-sm text-primary hover:bg-accent/40 hover:underline"
                >
                    View all notifications
                    <ChevronRight className="size-3.5" />
                </Link>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
