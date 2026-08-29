import { Link, router, usePage } from '@inertiajs/react';
import { Bell, ChevronRight } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { index as notificationsIndex, markAllRead } from '@/actions/App/Http/Controllers/NotificationController';
import { NotificationRow } from '@/components/notification-row';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Skeleton } from '@/components/ui/skeleton';
import { useNotificationRead } from '@/hooks/use-notification-read';

/**
 * A user parked on one page (not navigating) used to never learn a new
 * notification had arrived — freshness came only from the shared
 * `notifications` prop (HandleInertiaRequests::share()) refreshing on normal
 * navigation, plus a one-shot reload when the panel opens. This now also
 * polls in the background (see the effect below), on the same async,
 * non-interrupting pattern use-document-updates.ts established — that hook
 * remains the primary Supabase Realtime swap point (see
 * resources/js/lib/realtime.ts); this is a second, independent poller for a
 * different prop and should move to Realtime alongside it.
 *
 * The prop itself is a tight, unread-first, ~8-row teaser
 * (HandleInertiaRequests::notificationsFor()) — the full, paginated,
 * filterable history lives at /notifications (NotificationController::index,
 * "View all notifications" below), same capped-teaser-vs-full-page split as
 * the dashboard's "Recent Activity" card vs. the Activity Log page.
 */

// notificationsFor() runs an unread count plus an 8-row query on every page
// in the app (HandleInertiaRequests.php) — notifications aren't the queue a
// user is actively staring at, so this can afford to be much slower than
// use-document-updates.ts's 5s document poll.
const NOTIFICATIONS_POLL_INTERVAL_MS = 45_000;

export function NotificationBell() {
    const { notifications } = usePage().props;
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);
    const { markRowRead, markManyRead, visitNotification, resetOptimisticReadIds, applyOptimistic } = useNotificationRead();

    const { items, staleIds } = applyOptimistic(notifications?.items ?? []);
    const unreadCount = Math.max(0, (notifications?.unreadCount ?? 0) - staleIds.size);

    // Read inside the poll/visibility callbacks below via a ref rather than
    // an effect dependency — restarting the interval every time the dropdown
    // opens/closes would reset its timing for no benefit; a ref just lets
    // the next tick see the current value instead.
    const openRef = useRef(open);

    useEffect(() => {
        openRef.current = open;
    }, [open]);

    useEffect(() => {
        function poll() {
            // Reordering rows (unread-first) out from under a user actively
            // reading the open dropdown would be jarring — the open-handler
            // reload above already guarantees freshness at the moment it
            // matters (when the panel is opened).
            if (openRef.current || document.visibilityState === 'hidden') {
                return;
            }

            // async: true is required, not cosmetic — see
            // use-document-updates.ts's docblock. The bell mounts on every
            // page, including review pages with an approve/reject/return
            // modal; Inertia's sync stream allows only one in-flight visit
            // and would cancel that submit outright if a poll tick landed on
            // top of it.
            router.reload({ only: ['notifications'], async: true });
        }

        const interval = setInterval(poll, NOTIFICATIONS_POLL_INTERVAL_MS);

        return () => clearInterval(interval);
    }, []);

    useEffect(() => {
        function handleVisibilityChange() {
            // The moment a backgrounded tab is looked at again is exactly
            // when a poll tick was most likely missed.
            if (document.visibilityState === 'visible' && !openRef.current) {
                router.reload({ only: ['notifications'], async: true });
            }
        }

        document.addEventListener('visibilitychange', handleVisibilityChange);

        return () => document.removeEventListener('visibilitychange', handleVisibilityChange);
    }, []);

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
