import { useCallback, useState } from 'react';

const storageKey = (userId: number | string): string =>
    `sdao:nav-groups:${userId}`;

function readStored(userId: number | string): Record<string, boolean> {
    try {
        const raw = window.localStorage.getItem(storageKey(userId));
        const parsed: unknown = raw ? JSON.parse(raw) : null;

        return parsed && typeof parsed === 'object'
            ? (parsed as Record<string, boolean>)
            : {};
    } catch {
        return {};
    }
}

/**
 * Open/closed state of one collapsible sidebar group, remembered per user in
 * localStorage (a per-viewer convenience, so a blocked or cleared store just
 * means groups start closed). A group that holds the current route starts
 * open regardless of what was stored.
 */
export function useNavGroupOpen(
    userId: number | string,
    groupTitle: string,
    containsActiveItem: boolean,
): [boolean, (open: boolean) => void] {
    const [open, setOpenState] = useState<boolean>(
        () => containsActiveItem || readStored(userId)[groupTitle] === true,
    );

    const setOpen = useCallback(
        (next: boolean) => {
            setOpenState(next);

            try {
                // Merge so the other groups' saved states are never clobbered.
                window.localStorage.setItem(
                    storageKey(userId),
                    JSON.stringify({
                        ...readStored(userId),
                        [groupTitle]: next,
                    }),
                );
            } catch {
                // Storage unavailable: the in-memory state still works.
            }
        },
        [userId, groupTitle],
    );

    return [open, setOpen];
}
