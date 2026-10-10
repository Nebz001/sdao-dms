import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /** Extra paths that should also select this item (e.g. a role's own dashboard URL). */
    alsoActiveOn?: string[];
    /** Count shown at the row's right edge. Zero still renders, muted. */
    badge?: number;
    /** `alert` paints a non-zero count red (Stuck Documents); default is the neutral brand count. */
    badgeTone?: 'default' | 'alert';
};

/** A collapsible sidebar group: one row (icon, label, chevron) that opens to its items. */
export type NavGroup = {
    title: string;
    icon: LucideIcon;
    items: NavItem[];
    /** The group's hub page (the student home cards open it). Groups with no hub omit it. */
    href?: NonNullable<InertiaLinkProps['href']>;
};

export type NavEntry = NavItem | NavGroup;

export type NavSection = {
    label: string;
    entries: NavEntry[];
};

export function isNavGroup(entry: NavEntry): entry is NavGroup {
    return 'items' in entry;
}

/** Shared by HandleInertiaRequests as `navCounts` (App\Support\NavCounts). */
export type NavCounts = {
    stuck: number;
    review: {
        registrations: number;
        renewals: number;
        calendars: number;
        reports: number;
        proposals: number;
    };
    accounts: { pending: number; officerChanges: number };
    documents: {
        registrations: number;
        renewals: number;
        calendars: number;
        proposals: number;
        reports: number;
        history: number;
    };
};
