import { Link, usePage } from '@inertiajs/react';
import {
    CalendarRange,
    ChevronDown,
    ChevronRight,
    GraduationCap,
} from 'lucide-react';
import { Fragment } from 'react';
import AppLogoLockup from '@/components/app-logo-lockup';
import { NotificationBell } from '@/components/notification-bell';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import type { Crumb } from '@/lib/nav-trail';
import { dashboard } from '@/routes';

/**
 * The top navbar of the student home page — the only page that drops the
 * sidebar. Everything the sidebar chrome carried still lives here: the
 * notification bell, the current term and academic year, and the user menu
 * (settings and the confirmed log out, via the same UserMenuContent).
 */
export function AppTopbar({ crumbs }: { crumbs: Crumb[] }) {
    const { auth, currentPeriod } = usePage().props;
    const getInitials = useInitials();
    const user = auth?.user;
    const firstName = user?.first_name ?? user?.name.split(' ')[0] ?? '';

    return (
        <header className="border-b bg-background">
            <div className="mx-auto flex h-16 w-full max-w-6xl items-center gap-3 px-4 sm:px-6">
                <div className="flex min-w-0 items-center gap-3">
                    <Link
                        href={dashboard()}
                        prefetch
                        aria-label="NU Lipa, back to Home"
                        className="shrink-0 rounded-md focus-visible:focus-ring"
                    >
                        <AppLogoLockup className="h-10" />
                    </Link>
                    <span className="hidden truncate font-semibold sm:inline">
                        Document System
                    </span>
                    <Separator
                        orientation="vertical"
                        className="hidden h-5 sm:block"
                    />
                    {/* Below md the trail collapses: the page's own "Back to"
                        link (see AppTopbarLayout) is the way up. */}
                    <nav
                        aria-label="Breadcrumb"
                        className="hidden min-w-0 md:block"
                    >
                        <ol className="flex items-center gap-1.5 text-sm">
                            {crumbs.map((crumb, index) => {
                                const isLast = index === crumbs.length - 1;

                                return (
                                    <Fragment key={`${crumb.title}-${index}`}>
                                        {index > 0 && (
                                            <ChevronRight
                                                aria-hidden
                                                className="size-3.5 shrink-0 text-muted-foreground"
                                            />
                                        )}
                                        <li className="min-w-0">
                                            {isLast || !crumb.href ? (
                                                <span
                                                    aria-current={
                                                        isLast
                                                            ? 'page'
                                                            : undefined
                                                    }
                                                    className="block truncate font-semibold"
                                                >
                                                    {crumb.title}
                                                </span>
                                            ) : (
                                                <Link
                                                    href={crumb.href}
                                                    prefetch
                                                    className="block truncate rounded-sm text-muted-foreground hover:text-foreground focus-visible:focus-ring"
                                                >
                                                    {crumb.title}
                                                </Link>
                                            )}
                                        </li>
                                    </Fragment>
                                );
                            })}
                        </ol>
                    </nav>
                </div>

                <div className="ml-auto flex items-center gap-2">
                    <NotificationBell />
                    <div className="hidden items-center gap-2 md:flex">
                        <Badge
                            variant="outline"
                            className="h-8 gap-1.5 rounded-md px-3 text-sm font-normal"
                        >
                            <CalendarRange aria-hidden className="size-4" />
                            {currentPeriod.term_label}
                        </Badge>
                        <Badge
                            variant="outline"
                            className="h-8 gap-1.5 rounded-md px-3 text-sm font-normal"
                        >
                            <GraduationCap aria-hidden className="size-4" />
                            {currentPeriod.academic_year}
                        </Badge>
                    </div>

                    {user && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="outline"
                                    className="h-10 gap-2 rounded-full pr-3 pl-1"
                                    aria-label={`Account menu for ${user.name}`}
                                    data-test="user-menu-button"
                                >
                                    <Avatar className="size-8">
                                        <AvatarImage src={user.avatar} alt="" />
                                        <AvatarFallback className="bg-neutral-200 text-xs text-black dark:bg-neutral-700 dark:text-white">
                                            {getInitials(user.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span className="hidden max-w-32 truncate text-sm font-medium sm:inline">
                                        {firstName}
                                    </span>
                                    <ChevronDown
                                        aria-hidden
                                        className="size-4 text-muted-foreground"
                                    />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                className="min-w-56 rounded-lg"
                                align="end"
                                // The logout item opens a ConfirmDialog on top
                                // of this menu; see nav-user.tsx for why focus
                                // must not return to the trigger.
                                onCloseAutoFocus={(e) => e.preventDefault()}
                            >
                                <UserMenuContent user={user} />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            </div>
        </header>
    );
}
