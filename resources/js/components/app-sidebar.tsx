import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    CalendarDays,
    FilePlus2,
    FileText,
    Files,
    FolderGit2,
    Inbox,
    LayoutGrid,
    UserPlus,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import * as activityCalendars from '@/routes/activity-calendars';
import * as activityProposals from '@/routes/activity-proposals';
import * as approvers from '@/routes/admin/approvers';
import * as calendar from '@/routes/calendar';
import * as officers from '@/routes/officers';
import * as registrations from '@/routes/registrations';
import * as renewals from '@/routes/renewals';
import * as reports from '@/routes/reports';
import * as reviewActivityCalendars from '@/routes/review/activity-calendars';
import * as reviewActivityProposals from '@/routes/review/activity-proposals';
import * as reviewRegistrations from '@/routes/review/registrations';
import * as reviewRenewals from '@/routes/review/renewals';
import * as reviewReports from '@/routes/review/reports';
import type { NavItem, RoleAssignment } from '@/types';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

/** Roles that take part in the activity-proposal approval chain (CLAUDE.md #8). */
const PROPOSAL_APPROVER_ROLES = new Set([
    'sdao_member',
    'adviser',
    'program_chair',
    'dean',
    'principal',
    'assistant_director_academic_services',
    'academic_director',
    'executive_director',
]);

export function AppSidebar() {
    const { auth } = usePage().props;
    const roles: RoleAssignment[] = auth?.roles ?? [];

    // The real source of truth for "currently an active student officer" is
    // OrganizationMembership.is_active (shared as auth.isActiveOfficer) — NOT
    // a role_assignments row, which has no status column and is never
    // updated once created (would go stale on officer turnover).
    const isStudentOfficer = auth?.isActiveOfficer ?? false;
    const isSdao = roles.some((r) => r.role === 'sdao_member');
    const reviewsProposals = roles.some((r) => PROPOSAL_APPROVER_ROLES.has(r.role));
    const adviserRole = roles.find((r) => r.role === 'adviser' && r.organization_id !== null);

    const sections: { label: string; items: NavItem[] }[] = [
        {
            label: 'Platform',
            items: [
                { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
                { title: 'Venue Calendar', href: calendar.index(), icon: CalendarDays },
            ],
        },
    ];

    if (isStudentOfficer) {
        sections.push({
            label: 'Submit',
            items: [
                { title: 'Submit Registration', href: registrations.create(), icon: FilePlus2 },
                { title: 'Submit Renewal', href: renewals.create(), icon: FilePlus2 },
                { title: 'Submit Activity Calendar', href: activityCalendars.create(), icon: FilePlus2 },
                { title: 'Submit Activity Proposal', href: activityProposals.create(), icon: FilePlus2 },
                { title: 'Submit Report', href: reports.create(), icon: FilePlus2 },
            ],
        });

        sections.push({
            label: 'My Documents',
            items: [
                { title: 'My Registrations', href: registrations.index(), icon: Files },
                { title: 'My Renewals', href: renewals.index(), icon: Files },
                { title: 'My Calendars', href: activityCalendars.index(), icon: Files },
                { title: 'My Proposals', href: activityProposals.index(), icon: Files },
                { title: 'My Reports', href: reports.index(), icon: Files },
            ],
        });
    }

    const reviewItems: NavItem[] = [];

    if (isSdao) {
        reviewItems.push(
            { title: 'Review Registrations', href: reviewRegistrations.index(), icon: FileText },
            { title: 'Review Renewals', href: reviewRenewals.index(), icon: FileText },
            { title: 'Review Calendars', href: reviewActivityCalendars.index(), icon: FileText },
            { title: 'Review Reports', href: reviewReports.index(), icon: FileText },
        );
    }

    if (reviewsProposals) {
        reviewItems.push({ title: 'Review Proposals', href: reviewActivityProposals.index(), icon: Inbox });
    }

    if (reviewItems.length > 0) {
        sections.push({ label: 'Review', items: reviewItems });
    }

    const manageItems: NavItem[] = [];

    if (adviserRole?.organization_id) {
        manageItems.push({
            title: 'Manage Officers',
            href: officers.index({ organization: adviserRole.organization_id }),
            icon: Users,
        });
    }

    if (isSdao) {
        manageItems.push({
            title: 'Provision Approvers',
            href: approvers.index(),
            icon: UserPlus,
        });
    }

    if (manageItems.length > 0) {
        sections.push({ label: 'Manage', items: manageItems });
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {sections.map((section) => (
                    <NavMain key={section.label} label={section.label} items={section.items} />
                ))}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
