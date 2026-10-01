import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

function pathOf(url: string): string {
    try {
        return new URL(url, 'http://localhost').pathname.replace(/\/+$/, '') || '/';
    } catch {
        return url;
    }
}

/**
 * Returns the one sidebar item that owns `currentPath`, or null.
 *
 * An item owns a path when the path equals its href (or one of its
 * `alsoActiveOn` paths), or sits underneath it (`/registrations/5/edit`
 * belongs to `/registrations`). When several items match, the longest href
 * wins, so `/registrations/create` stays with "Submit Registration" instead
 * of also lighting up "My Registrations". Only one item is ever selected,
 * across every sidebar section.
 */
export function resolveActiveNavItem(
    items: NavItem[],
    currentPath: string,
): NavItem | null {
    const path = pathOf(currentPath);
    let best: { item: NavItem; length: number } | null = null;

    for (const item of items) {
        const candidates = [toUrl(item.href), ...(item.alsoActiveOn ?? [])].map(
            pathOf,
        );

        for (const candidate of candidates) {
            const matches =
                path === candidate ||
                (candidate !== '/' && path.startsWith(`${candidate}/`));

            if (matches && (best === null || candidate.length > best.length)) {
                best = { item, length: candidate.length };
            }
        }
    }

    return best?.item ?? null;
}
