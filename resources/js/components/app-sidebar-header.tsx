import { usePage } from '@inertiajs/react';
import { CalendarRange, GraduationCap } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Badge } from '@/components/ui/badge';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { currentTerm, academicYear } = usePage().props;

    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            {/* The only thing left in this slot now that the date is gone —
                real badge treatment (icon + pill) instead of plain gray text,
                so it reads as intentional persistent context rather than a
                leftover label. */}
            <div className="ml-auto hidden items-center gap-2 sm:flex">
                <Badge variant="secondary" className="gap-1.5 font-normal">
                    <CalendarRange aria-hidden />
                    {currentTerm}
                </Badge>
                <Badge variant="secondary" className="gap-1.5 font-normal">
                    <GraduationCap aria-hidden />
                    {academicYear}
                </Badge>
            </div>
        </header>
    );
}
