import { usePage } from '@inertiajs/react';
import { CalendarRange, GraduationCap } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { NotificationBell } from '@/components/notification-bell';
import { Badge } from '@/components/ui/badge';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { currentPeriod } = usePage().props;

    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            {/* Always visible (unlike the term/year pills below, which stay
                hidden below sm) — every logged-in role needs the bell,
                including on mobile. */}
            <div className="ml-auto flex items-center gap-2">
                <NotificationBell />
                {/* Real badge treatment (icon + pill) instead of plain gray
                    text, so it reads as intentional persistent context
                    rather than a leftover label. */}
                <div className="hidden items-center gap-2 sm:flex">
                    <Badge variant="secondary" className="gap-1.5 font-normal">
                        <CalendarRange aria-hidden />
                        {currentPeriod.term_label}
                    </Badge>
                    <Badge variant="secondary" className="gap-1.5 font-normal">
                        <GraduationCap aria-hidden />
                        {currentPeriod.academic_year}
                    </Badge>
                </div>
            </div>
        </header>
    );
}
