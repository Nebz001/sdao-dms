import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useEffect } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useNavGroupOpen } from '@/hooks/use-nav-group-open';
import { cn } from '@/lib/utils';
import type { NavEntry, NavGroup, NavItem } from '@/types';
import { isNavGroup } from '@/types/navigation';

/**
 * Count pill at a row's right edge. Zero still renders, in the muted style,
 * so "nothing waiting" reads as information rather than a missing badge.
 */
function NavBadge({
    count,
    tone = 'default',
    active = false,
}: {
    count: number;
    tone?: NavItem['badgeTone'];
    active?: boolean;
}) {
    return (
        <span
            data-slot="nav-badge"
            className={cn(
                'ml-auto inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full px-1.5 text-xs font-medium tabular-nums group-data-[collapsible=icon]:hidden',
                count === 0
                    ? 'bg-muted text-muted-foreground'
                    : active
                      ? 'bg-sidebar-primary-foreground/20 text-sidebar-primary-foreground'
                      : tone === 'alert'
                        ? 'bg-destructive text-white'
                        : 'bg-primary text-primary-foreground',
            )}
        >
            {count}
        </span>
    );
}

function NavRow({ item }: { item: NavItem }) {
    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                asChild
                isActive={item.isActive ?? false}
                tooltip={{ children: item.title }}
            >
                <Link href={item.href} prefetch>
                    {item.icon && <item.icon />}
                    <span>{item.title}</span>
                    {item.badge !== undefined && (
                        <NavBadge
                            count={item.badge}
                            tone={item.badgeTone}
                            active={item.isActive}
                        />
                    )}
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    );
}

function NavGroupRow({
    group,
    userId,
}: {
    group: NavGroup;
    userId: number | string;
}) {
    const { state, isMobile } = useSidebar();
    const containsActiveItem = group.items.some((item) => item.isActive);
    const [open, setOpen] = useNavGroupOpen(
        userId,
        group.title,
        containsActiveItem,
    );

    // Navigating INTO a group (a link elsewhere, back/forward) opens it.
    // Keyed on the flag only, so a person can still close the group they are
    // standing in.
    useEffect(() => {
        if (containsActiveItem) {
            setOpen(true);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [containsActiveItem]);

    const total = group.items.reduce((sum, item) => sum + (item.badge ?? 0), 0);
    const hasBadges = group.items.some((item) => item.badge !== undefined);

    // Icon-only sidebar: there is no room to push a list down, so the group
    // opens as a flyout beside the rail instead.
    if (state === 'collapsed' && !isMobile) {
        return (
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            isActive={containsActiveItem}
                            aria-label={group.title}
                        >
                            <group.icon />
                            <span>{group.title}</span>
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        side="right"
                        align="start"
                        sideOffset={8}
                        className="min-w-52"
                    >
                        <DropdownMenuLabel>{group.title}</DropdownMenuLabel>
                        <DropdownMenuGroup>
                            {group.items.map((item) => (
                                <DropdownMenuItem
                                    key={item.title}
                                    asChild
                                    className={cn(
                                        item.isActive &&
                                            'bg-accent font-medium',
                                    )}
                                >
                                    <Link href={item.href} prefetch>
                                        <div className="min-w-0 flex-1 leading-5">
                                            {item.title}
                                        </div>
                                        {item.badge !== undefined && (
                                            <NavBadge
                                                count={item.badge}
                                                tone={item.badgeTone}
                                            />
                                        )}
                                    </Link>
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuGroup>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        );
    }

    return (
        <Collapsible
            asChild
            open={open}
            onOpenChange={setOpen}
            className="group/collapsible"
        >
            <SidebarMenuItem>
                {/* Radix puts aria-expanded / aria-controls on the trigger and
                    wires Enter / Space, so the row is keyboard operable and
                    announces its state with no extra ARIA here. */}
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton tooltip={{ children: group.title }}>
                        <group.icon />
                        <span>{group.title}</span>
                        <span className="ml-auto flex items-center gap-1.5">
                            {/* While closed, the group still signals work. */}
                            {!open && hasBadges && total > 0 && (
                                <NavBadge count={total} />
                            )}
                            <ChevronRight
                                aria-hidden
                                className="transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90 motion-reduce:transition-none"
                            />
                        </span>
                    </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <SidebarMenuSub className="my-1.5 gap-1 py-1">
                        {group.items.map((item) => (
                            <SidebarMenuSubItem key={item.title}>
                                <SidebarMenuSubButton
                                    asChild
                                    isActive={item.isActive ?? false}
                                    className="h-auto min-h-8 items-start py-1.5"
                                >
                                    <Link href={item.href} prefetch>
                                        <div className="min-w-0 flex-1 text-sm leading-5 [overflow-wrap:anywhere]">
                                            {item.title}
                                        </div>
                                        {item.badge !== undefined && (
                                            <NavBadge
                                                count={item.badge}
                                                tone={item.badgeTone}
                                                active={item.isActive}
                                            />
                                        )}
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}

export function NavMain({
    entries = [],
    label = 'Platform',
    userId,
}: {
    entries: NavEntry[];
    label?: string;
    userId: number | string;
}) {
    return (
        <SidebarGroup className="px-2 pt-4 pb-0 first:pt-0">
            <SidebarGroupLabel>{label}</SidebarGroupLabel>
            <SidebarMenu>
                {entries.map((entry) =>
                    isNavGroup(entry) ? (
                        <NavGroupRow
                            key={entry.title}
                            group={entry}
                            userId={userId}
                        />
                    ) : (
                        <NavRow key={entry.title} item={entry} />
                    ),
                )}
            </SidebarMenu>
        </SidebarGroup>
    );
}
