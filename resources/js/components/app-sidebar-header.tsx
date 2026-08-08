import { usePage } from '@inertiajs/react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

/**
 * Today's date, computed client-side rather than passed from the server —
 * this is purely presentational persistent context, so the viewer's own
 * clock/timezone is more correct here than round-tripping the server's.
 */
function today(): string {
    return new Date().toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

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
            <div className="ml-auto hidden items-center gap-2 text-sm text-muted-foreground sm:flex">
                <span>{currentTerm}</span>
                <span aria-hidden>·</span>
                <span>{academicYear}</span>
                <span aria-hidden>·</span>
                <span>{today()}</span>
            </div>
        </header>
    );
}
