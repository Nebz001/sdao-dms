import { resolveActiveNavItem } from '@/lib/active-nav';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { BreadcrumbItem, NavItem, NavSection } from '@/types';
import { isNavGroup } from '@/types/navigation';

export type PageCrumb = { title: string; href?: BreadcrumbItem['href'] };

export type Crumb = { title: string; href?: BreadcrumbItem['href'] };

export type Trail = {
    /** Home first, the current page last (the last crumb has no href). */
    crumbs: Crumb[];
    /** The crumb before the current page, for "Back to {parent}". Null on Home. */
    parent: (Crumb & { href: BreadcrumbItem['href'] }) | null;
};

function pathOf(url: string): string {
    try {
        return (
            new URL(url, 'http://localhost').pathname.replace(/\/+$/, '') || '/'
        );
    } catch {
        return url;
    }
}

/** A page under an item (`/registrations/5/edit`) that the page itself did not name. */
function tailLabel(path: string): string {
    if (path.endsWith('/create')) {
        return 'New';
    }

    if (path.endsWith('/edit')) {
        return 'Edit';
    }

    return 'Details';
}

/**
 * The breadcrumb trail for the current URL, derived from the same nav sections
 * that build the sidebar and the home cards: Home, then the item's group (when
 * the group has a hub and more than one option), then the item. A page below an
 * item adds one last crumb. A page that is in no nav entry (notifications,
 * settings) falls back to Home and the title the page supplied.
 *
 * `pageCrumbs` are the page's own `layout.breadcrumbs`; only their last title
 * is used, and only when nothing in the nav config names the page.
 */
export function buildTrail(
    sections: NavSection[],
    url: string,
    pageCrumbs: PageCrumb[] = [],
): Trail {
    const home: Crumb = { title: 'Home', href: dashboard() };
    const path = pathOf(url);
    const pageTitle = pageCrumbs.at(-1)?.title;

    const entries = sections.flatMap((section) => section.entries);
    const owners = new Map<NavItem, (typeof entries)[number] | null>();

    for (const entry of entries) {
        if (isNavGroup(entry)) {
            entry.items.forEach((item) => owners.set(item, entry));
        } else if (entry.title !== 'Dashboard') {
            owners.set(entry, null);
        }
    }

    // A hub page is its group's own href, not an item in the group.
    const hubGroup = entries.find(
        (entry) =>
            isNavGroup(entry) &&
            entry.href !== undefined &&
            pathOf(toUrl(entry.href)) === path,
    );

    if (hubGroup) {
        return finish([home, { title: hubGroup.title }]);
    }

    const item = resolveActiveNavItem([...owners.keys()], url);

    if (!item) {
        if (path === pathOf(toUrl(dashboard()))) {
            return finish([{ title: 'Home' }]);
        }

        return finish([home, { title: pageTitle ?? 'Page' }]);
    }

    const group = owners.get(item);
    const crumbs: Crumb[] = [home];

    if (group && isNavGroup(group) && group.items.length > 1 && group.href) {
        crumbs.push({ title: group.title, href: group.href });
    }

    if (pathOf(toUrl(item.href)) === path) {
        crumbs.push({ title: item.title });
    } else {
        crumbs.push({ title: item.title, href: item.href });
        crumbs.push({
            title:
                pageCrumbs.length > 1 && pageTitle
                    ? pageTitle
                    : tailLabel(path),
        });
    }

    return finish(crumbs);
}

function finish(crumbs: Crumb[]): Trail {
    const parent = crumbs.at(-2);

    return {
        crumbs,
        parent: parent?.href ? { ...parent, href: parent.href } : null,
    };
}
