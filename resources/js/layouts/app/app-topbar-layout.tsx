import { Link, usePage } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { useMemo } from 'react';
import { AppTopbar } from '@/components/app-topbar';
import { MAX_WIDTHS } from '@/components/centered-container';
import IdleTimeoutDialog from '@/components/idle-timeout-dialog';
import { Toaster } from '@/components/ui/toaster';
import { buildNavSections } from '@/lib/nav-config';
import { buildTrail } from '@/lib/nav-trail';
import type { PageCrumb } from '@/lib/nav-trail';
import { cn } from '@/lib/utils';

/**
 * The one layout every student page uses (see app.tsx): a top navbar with the
 * breadcrumb, and under it a "Back to {parent}" link on every page below Home.
 * Both come from the same nav config that drives the sidebar and the home
 * cards, so none of the screens spell out their own trail. Mounts the same
 * Toaster and idle-timeout dialog as the sidebar layout.
 *
 * `breadcrumbs` are the page's own layout props. They are only a fallback
 * label for a page the nav config does not list (see buildTrail()).
 */
export default function AppTopbarLayout({
    children,
    breadcrumbs = [],
    columnWidth,
}: {
    children: React.ReactNode;
    breadcrumbs?: PageCrumb[];
    /**
     * A page that lays its content out in a narrower centered column (the
     * filing forms) names that column's width here, and the "Back to …" link
     * is placed in a column of the same width so it lines up with the left
     * edge of the content instead of the far left of the page.
     */
    columnWidth?: keyof typeof MAX_WIDTHS;
}) {
    const page = usePage();
    const { auth, navCounts } = page.props;
    const trail = useMemo(
        () =>
            buildTrail(
                buildNavSections(auth, navCounts),
                page.url,
                breadcrumbs,
            ),
        [auth, navCounts, page.url, breadcrumbs],
    );

    return (
        <div className="flex min-h-svh flex-col bg-background">
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-background focus:px-3 focus:py-2 focus:text-sm focus:shadow-md"
            >
                Skip to content
            </a>
            <AppTopbar crumbs={trail.crumbs} />
            <main
                id="main-content"
                className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 px-4 py-6 sm:px-6 sm:py-8"
            >
                {trail.parent && (
                    <div
                        className={cn(
                            '-mb-2',
                            columnWidth &&
                                `mx-auto w-full ${MAX_WIDTHS[columnWidth]}`,
                        )}
                    >
                        <Link
                            href={trail.parent.href}
                            prefetch
                            className="inline-flex w-fit items-center gap-1 rounded-sm text-sm font-medium text-muted-foreground hover:text-foreground focus-visible:focus-ring"
                        >
                            <ChevronLeft aria-hidden className="size-4" />
                            Back to {trail.parent.title}
                        </Link>
                    </div>
                )}
                {children}
            </main>
            <Toaster />
            <IdleTimeoutDialog />
        </div>
    );
}
