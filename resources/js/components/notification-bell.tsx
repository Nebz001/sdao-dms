import { router, usePage } from '@inertiajs/react';
import { Bell, Check } from 'lucide-react';
import { useState } from 'react';
import { markAllRead, markRead } from '@/actions/App/Http/Controllers/NotificationController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Skeleton } from '@/components/ui/skeleton';
import { cn, formatRelativeTime } from '@/lib/utils';
import type { NotificationItem } from '@/types/notifications';

/**
 * The bell's data is deliberately NOT kept fresh by a background poll — see
 * use-document-updates.ts for the pattern this intentionally does not reuse.
 * Freshness instead comes from two cheap sources: the shared `notifications`
 * prop (HandleInertiaRequests::share()) refreshing on every normal page
 * navigation, and a one-shot partial reload fired right here when the panel
 * is actually opened, so what's shown is never more than one click stale.
 */
export function NotificationBell() {
    const { notifications } = usePage().props;
    const [loading, setLoading] = useState(false);

    const unreadCount = notifications?.unreadCount ?? 0;
    const items = notifications?.items ?? [];

    function handleOpenChange(open: boolean) {
        if (!open) {
            return;
        }

        setLoading(true);
        router.reload({
            only: ['notifications'],
            async: true,
            onFinish: () => setLoading(false),
        });
    }

    function handleMarkRead(item: NotificationItem) {
        if (item.readAt) {
            return;
        }

        router.patch(markRead.url(item.id), {}, { preserveScroll: true, preserveState: true });
    }

    function handleRowClick(item: NotificationItem) {
        handleMarkRead(item);

        if (item.url) {
            router.visit(item.url);
        }
    }

    function handleMarkAllRead() {
        router.patch(markAllRead.url(), {}, { preserveScroll: true });
    }

    return (
        <DropdownMenu onOpenChange={handleOpenChange}>
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
            <DropdownMenuContent align="end" className="w-80 p-0">
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
                    <ul className="max-h-[380px] overflow-y-auto py-1">
                        {items.map((item) => (
                            <li
                                key={item.id}
                                className={cn(
                                    'flex items-start gap-2 px-3 py-2.5 hover:bg-accent',
                                    !item.readAt && 'bg-accent/40',
                                )}
                            >
                                <span
                                    className={cn('mt-1.5 size-2 shrink-0 rounded-full', !item.readAt && 'bg-primary')}
                                    aria-hidden
                                />
                                <button
                                    type="button"
                                    onClick={() => handleRowClick(item)}
                                    className="min-w-0 flex-1 text-left text-sm"
                                >
                                    <span
                                        className={cn(
                                            'block truncate font-medium',
                                            item.readAt && 'font-normal text-muted-foreground',
                                        )}
                                    >
                                        {item.title}
                                    </span>
                                    <span className="block truncate text-xs text-muted-foreground">{item.body}</span>
                                    <span className="block text-xs text-muted-foreground">
                                        {formatRelativeTime(item.createdAt)}
                                    </span>
                                </button>
                                {!item.readAt && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="size-6 shrink-0"
                                        aria-label="Mark as read"
                                        onClick={() => handleMarkRead(item)}
                                    >
                                        <Check />
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
