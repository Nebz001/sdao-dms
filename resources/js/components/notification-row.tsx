import { Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useRelativeTime } from '@/hooks/use-relative-time';
import { notificationVisual } from '@/lib/notifications';
import { cn } from '@/lib/utils';
import type { NotificationItem } from '@/types/notifications';

type Props = {
    item: NotificationItem;
    onRowClick: () => void;
    onMarkRead: () => void;
};

/**
 * A single notification row — shared by the bell dropdown and the full
 * /notifications page (see notification-bell.tsx and
 * pages/notifications/index.tsx) so the two surfaces can never show a
 * different read/unread treatment for the same notification.
 *
 * Unread vs. read is deliberately not a subtle distinction: bold
 * full-foreground title, a tinted icon chip, an unread dot, and a colored
 * left border strip for unread; all of that drops away — muted text, plain
 * icon, no dot, transparent border — the moment a row is read, so the two
 * states read as unmistakably different at a glance, not just on close
 * inspection.
 */
export function NotificationRow({ item, onRowClick, onMarkRead }: Props) {
    const isRead = Boolean(item.readAt);
    const { Icon, iconClassName, chipClassName } = notificationVisual(item);
    const relativeTime = useRelativeTime(item.createdAt);

    return (
        <li
            className={cn(
                'flex items-start gap-3 border-b border-l-4 border-border/70 px-3 py-3 transition-colors last:border-b-0 hover:bg-accent/40',
                isRead ? 'border-l-transparent' : 'border-l-primary bg-primary/[0.07]',
            )}
        >
            <span
                className={cn(
                    'mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full',
                    isRead ? 'bg-transparent' : chipClassName,
                )}
            >
                <Icon className={cn('size-4', isRead ? 'text-muted-foreground/50' : iconClassName)} aria-hidden />
            </span>
            <button type="button" onClick={onRowClick} className="min-w-0 flex-1 text-left">
                <div className="flex items-start justify-between gap-2">
                    <span
                        className={cn(
                            'line-clamp-2 text-sm',
                            isRead ? 'font-normal text-muted-foreground' : 'font-semibold text-foreground',
                        )}
                    >
                        {item.title}
                    </span>
                    {!isRead && <span className="mt-1.5 size-2 shrink-0 rounded-full bg-primary" aria-hidden />}
                </div>
                <span className={cn('mt-0.5 block truncate text-xs', isRead ? 'text-muted-foreground/70' : 'text-muted-foreground')}>
                    {item.body}
                </span>
                <span className="mt-0.5 block text-[11px] text-muted-foreground/70">{relativeTime}</span>
            </button>
            {!isRead && (
                <Button variant="ghost" size="icon" className="size-6 shrink-0" aria-label="Mark as read" onClick={onMarkRead}>
                    <Check />
                </Button>
            )}
        </li>
    );
}
