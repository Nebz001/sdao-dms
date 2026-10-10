import { Link, router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import OrgBranding from '@/components/org-branding';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { resolveActiveNavItem } from '@/lib/active-nav';
import { buildNavSections } from '@/lib/nav-config';
import { dashboard } from '@/routes';
import type { NavItem, NavSection } from '@/types';
import { isNavGroup } from '@/types/navigation';

/** How often the sidebar refreshes its count badges. */
const NAV_COUNTS_POLL_MS = 15_000;

export function AppSidebar() {
    const page = usePage();
    const { auth } = page.props;
    const sections = buildNavSections(auth, page.props.navCounts);

    // Badges go stale on their own (another approver acts, a student files),
    // so the sidebar refreshes just its counts. A closure prop on the server,
    // so nothing else is recomputed. Skipped for a hidden tab.
    const hasBadges = sections.some((section) =>
        section.entries.some((entry) =>
            isNavGroup(entry)
                ? entry.items.some((item) => item.badge !== undefined)
                : entry.badge !== undefined,
        ),
    );

    useEffect(() => {
        if (!hasBadges) {
            return;
        }

        const interval = setInterval(() => {
            if (document.visibilityState === 'visible') {
                router.reload({ only: ['navCounts'], async: true });
            }
        }, NAV_COUNTS_POLL_MS);

        return () => clearInterval(interval);
    }, [hasBadges]);

    // One selected item across all sections, resolved from the current path.
    const activeItem = resolveActiveNavItem(
        sections.flatMap((section) =>
            section.entries.flatMap((entry) =>
                isNavGroup(entry) ? entry.items : [entry],
            ),
        ),
        page.url,
    );
    const withActive = (item: NavItem): NavItem => ({
        ...item,
        isActive: item === activeItem,
    });
    const sectionsWithActive: NavSection[] = sections.map((section) => ({
        ...section,
        entries: section.entries.map((entry) =>
            isNavGroup(entry)
                ? { ...entry, items: entry.items.map(withActive) }
                : withActive(entry),
        ),
    }));

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        {/* h-auto: the subtitle now wraps to two lines
                            (see app-logo.tsx), so a fixed height would clip
                            it — let the button grow to fit its content. */}
                        <SidebarMenuButton size="lg" className="h-auto" asChild>
                            <Link href={dashboard()} prefetch>
                                {auth?.organization ? (
                                    <OrgBranding
                                        organization={auth.organization}
                                    />
                                ) : (
                                    <AppLogo />
                                )}
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {sectionsWithActive.map((section) => (
                    <NavMain
                        key={section.label}
                        label={section.label}
                        entries={section.entries}
                        userId={auth?.user?.id ?? 'guest'}
                    />
                ))}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
