import { router } from '@inertiajs/react';
import { useState } from 'react';
import { markRead } from '@/actions/App/Http/Controllers/NotificationController';
import { xsrfToken } from '@/lib/notifications';
import type { NotificationItem } from '@/types/notifications';

/**
 * Mark-as-read + navigate-to-document behavior shared by every notification
 * surface — the bell dropdown and the full /notifications page — so the two
 * can never drift into different behavior. One optimistic overlay, one
 * fetch, one navigation path.
 */
export function useNotificationRead() {
    // Ids marked read locally, ahead of the server round trip. Never pruned
    // in place — applyOptimistic() below derives, on every call, which of
    // these the server hasn't confirmed yet, so a stale id left in here is
    // harmless rather than something that must be cleaned up eagerly.
    const [optimisticReadIds, setOptimisticReadIds] = useState<Set<string>>(new Set());

    function markRowRead(item: Pick<NotificationItem, 'id' | 'readAt'>) {
        if (item.readAt) {
            return;
        }

        // Optimistic first: the row dims, its unread dot disappears, and
        // the badge/count drops, immediately — not after the request (or a
        // future reload) resolves.
        setOptimisticReadIds((prev) => new Set(prev).add(item.id));

        // Plain fetch, not router.patch — a row click also fires
        // router.visit(item.url) right after this (see visitNotification).
        // Inertia's router only keeps one visit in flight and cancels the
        // previous one, so router.patch here would race router.visit and
        // could win, leaving the user on markRead's back()-redirect target
        // instead of the notification's actual destination. Firing this as
        // a plain, uncancellable request sidesteps that entirely — same
        // pattern as ImmediateAttachmentUpload and the step-two draft ping.
        fetch(markRead.url(item.id), {
            method: 'PATCH',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
        });
    }

    function markManyRead(items: Pick<NotificationItem, 'id' | 'readAt'>[]) {
        setOptimisticReadIds((prev) => {
            const next = new Set(prev);
            items.forEach((item) => {
                if (!item.readAt) {
                    next.add(item.id);
                }
            });

            return next;
        });
    }

    function visitNotification(item: NotificationItem, beforeVisit?: () => void) {
        markRowRead(item);
        beforeVisit?.();

        if (item.url) {
            router.visit(item.url);
        }
    }

    function resetOptimisticReadIds() {
        setOptimisticReadIds(new Set());
    }

    /**
     * Merges the optimistic overlay onto a server items array, pruning ids
     * the server has already confirmed read. A row click's own navigation
     * re-evaluates the page's `notifications` data — if the fire-and-forget
     * markRead PATCH has already committed by then, the fresh payload
     * already excludes the item from its unread count, and masking it again
     * with the raw optimistic set would double-count it (badge drops by 2
     * instead of 1). Deriving this per call, rather than pruning
     * optimisticReadIds itself, keeps the overlay masking only what the
     * server hasn't caught up to yet without an extra render pass.
     */
    function applyOptimistic<T extends { id: string; readAt: string | null }>(
        items: T[],
    ): { items: T[]; staleIds: Set<string> } {
        if (optimisticReadIds.size === 0) {
            return { items, staleIds: optimisticReadIds };
        }

        const serverConfirmed = new Set(items.filter((item) => item.readAt).map((item) => item.id));
        const staleIds = new Set([...optimisticReadIds].filter((id) => !serverConfirmed.has(id)));
        const merged = items.map((item) =>
            !item.readAt && staleIds.has(item.id) ? { ...item, readAt: new Date().toISOString() } : item,
        );

        return { items: merged, staleIds };
    }

    return {
        optimisticReadIds,
        markRowRead,
        markManyRead,
        visitNotification,
        resetOptimisticReadIds,
        applyOptimistic,
    };
}
