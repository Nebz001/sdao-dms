import { Link, router, usePage } from '@inertiajs/react';
import {
    Archive,
    Building2,
    CalendarCog,
    ClipboardCheck,
    CalendarDays,
    FilePlus2,
    Files,
    History,
    LayoutGrid,
    UserCheck,
    UserPlus,
    Users,
    UserRoundCog,
    TriangleAlert,
} from 'lucide-react';
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
import { dashboard } from '@/routes';
import * as activityCalendars from '@/routes/activity-calendars';
import * as activityProposals from '@/routes/activity-proposals';
import * as activityLog from '@/routes/admin/activity';
import * as approvers from '@/routes/admin/approvers';
import * as archive from '@/routes/admin/archive';
import * as adminDashboard from '@/routes/admin/dashboard';
import * as adminOfficerChangeRequests from '@/routes/admin/officer-change-requests';
import * as adminOrganizations from '@/routes/admin/organizations';
import * as pendingAccounts from '@/routes/admin/pending-accounts';
import * as currentPeriodSettings from '@/routes/admin/settings/period';
import * as stuckDocuments from '@/routes/admin/stuck-documents';
import * as calendar from '@/routes/calendar';
import * as documentHistory from '@/routes/document-history';
import * as officers from '@/routes/officers';
import * as myOrganization from '@/routes/organizations';
import * as organizationsJoin from '@/routes/organizations/join';
import * as officerChange from '@/routes/organizations/officer-change';
import * as registrations from '@/routes/registrations';
import * as renewals from '@/routes/renewals';
import * as reports from '@/routes/reports';
import * as reviewActivityCalendars from '@/routes/review/activity-calendars';
import * as reviewActivityProposals from '@/routes/review/activity-proposals';
import * as reviewJoinRequests from '@/routes/review/join-requests';
import * as reviewRegistrations from '@/routes/review/registrations';
import * as reviewRenewals from '@/routes/review/renewals';
import * as reviewReports from '@/routes/review/reports';
import type { NavEntry, NavItem, NavSection, RoleAssignment } from '@/types';
import { isNavGroup } from '@/types/navigation';

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

/** How often the sidebar refreshes its count badges. */
const NAV_COUNTS_POLL_MS = 15_000;

export function AppSidebar() {
    const page = usePage();
    const { auth } = page.props;
    const roles: RoleAssignment[] = auth?.roles ?? [];

    // The real source of truth for "currently an active student officer" is
    // OrganizationMembership.is_active (shared as auth.isActiveOfficer) — NOT
    // a role_assignments row, which has no status column and is never
    // updated once created (would go stale on officer turnover).
    const isStudentOfficer = auth?.isActiveOfficer ?? false;
    const isSdao = roles.some((r) => r.role === 'sdao_member');
    const holdsApproverRole = roles.some((r) =>
        PROPOSAL_APPROVER_ROLES.has(r.role),
    );
    // An adviser in the unassigned pool (or one just replaced) has no
    // organization, so there is nothing for them to review — the proposal
    // review entries stay hidden until they are bound to one.
    const reviewsProposals = roles.some(
        (r) =>
            PROPOSAL_APPROVER_ROLES.has(r.role) &&
            !(r.role === 'adviser' && r.organization_id === null),
    );
    const adviserRole = roles.find(
        (r) => r.role === 'adviser' && r.organization_id !== null,
    );

    // A verified student with no org and no approver role yet — eligible to
    // found a new organization (DocumentPolicy::propose, shared server-side
    // as auth.canProposeOrganization). Guarded against isSdao/holdsApproverRole
    // too: those roles use RoleAssignment, not OrganizationMembership, so
    // they'd also read as "no active org" without this extra check — the
    // founding flow is student-only (an unassigned adviser included).
    const canFoundOrganization =
        !isStudentOfficer &&
        !isSdao &&
        !holdsApproverRole &&
        (auth?.canProposeOrganization ?? false);

    const counts = page.props.navCounts;
    const review = counts?.review;
    const documents = counts?.documents;

    // Platform: the same for everyone, plus Stuck Documents for SDAO.
    const platformItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
            // SDAO members are sent to the admin dashboard from /dashboard.
            alsoActiveOn: [adminDashboard.index.url()],
        },
        {
            title: 'Venue Calendar',
            href: calendar.index(),
            icon: CalendarDays,
        },
    ];

    if (isSdao) {
        platformItems.push({
            title: 'Stuck Documents',
            href: stuckDocuments.index(),
            icon: TriangleAlert,
            badge: counts?.stuck ?? 0,
            badgeTone: 'alert',
        });
    }

    // Workspace: collapsible groups first, then plain single rows.
    const workspaceEntries: NavEntry[] = [];
    const workspaceRows: NavItem[] = [];

    if (isStudentOfficer) {
        workspaceEntries.push(
            {
                title: 'Submit',
                icon: FilePlus2,
                items: [
                    {
                        title: 'Registration',
                        href: registrations.create(),
                    },
                    { title: 'Renewal', href: renewals.create() },
                    {
                        title: 'Activity Calendar',
                        href: activityCalendars.create(),
                    },
                    {
                        title: 'Activity Proposal',
                        href: activityProposals.create(),
                    },
                    { title: 'Report', href: reports.create() },
                ],
            },
            {
                title: 'My Documents',
                icon: Files,
                items: [
                    {
                        title: 'Registrations',
                        href: registrations.index(),
                        badge: documents?.registrations ?? 0,
                    },
                    {
                        title: 'Renewals',
                        href: renewals.index(),
                        badge: documents?.renewals ?? 0,
                    },
                    {
                        title: 'Calendars',
                        href: activityCalendars.index(),
                        badge: documents?.calendars ?? 0,
                    },
                    {
                        title: 'Proposals',
                        href: activityProposals.index(),
                        badge: documents?.proposals ?? 0,
                    },
                    {
                        title: 'Reports',
                        href: reports.index(),
                        badge: documents?.reports ?? 0,
                    },
                    {
                        title: 'Document History',
                        href: documentHistory.index(),
                        badge: documents?.history ?? 0,
                    },
                ],
            },
        );

        workspaceRows.push(
            {
                title: 'My Organization',
                href: myOrganization.mine(),
                icon: Building2,
            },
            {
                title: 'Request Officer Change',
                href: officerChange.create(),
                icon: UserRoundCog,
            },
        );
    } else if (canFoundOrganization) {
        workspaceEntries.push(
            {
                title: 'Submit',
                icon: FilePlus2,
                items: [
                    {
                        title: 'Registration',
                        href: registrations.create(),
                    },
                ],
            },
            {
                title: 'My Documents',
                icon: Files,
                items: [
                    {
                        title: 'Registrations',
                        href: registrations.index(),
                        badge: documents?.registrations ?? 0,
                    },
                ],
            },
        );

        workspaceRows.push({
            title: 'Join an Organization',
            href: organizationsJoin.create(),
            icon: UserPlus,
        });
    }

    const reviewItems: NavItem[] = [];

    if (isSdao) {
        reviewItems.push(
            {
                title: 'Registrations',
                href: reviewRegistrations.index(),
                badge: review?.registrations ?? 0,
            },
            {
                title: 'Renewals',
                href: reviewRenewals.index(),
                badge: review?.renewals ?? 0,
            },
            {
                title: 'Calendars',
                href: reviewActivityCalendars.index(),
                badge: review?.calendars ?? 0,
            },
            {
                title: 'Reports',
                href: reviewReports.index(),
                badge: review?.reports ?? 0,
            },
        );
    }

    if (reviewsProposals) {
        reviewItems.push({
            title: 'Proposals',
            href: reviewActivityProposals.index(),
            badge: review?.proposals ?? 0,
        });
    }

    // Join requests are decided by an org's adviser OR any of its active
    // officers (DocumentPolicy::manageJoinRequests) — broader than
    // manageOfficers' adviser-only "Manage Officers" entry below, so this
    // gates on isStudentOfficer too, not just adviserRole.
    if (adviserRole?.organization_id || isStudentOfficer) {
        reviewItems.push({
            title: 'Join Requests',
            href: reviewJoinRequests.index(),
        });
    }

    if (reviewItems.length > 0) {
        workspaceEntries.unshift({
            title: 'Review',
            icon: ClipboardCheck,
            items: reviewItems,
        });
    }

    if (isSdao) {
        workspaceEntries.push({
            title: 'Accounts',
            icon: UserCheck,
            items: [
                {
                    title: 'Pending Accounts',
                    href: pendingAccounts.index(),
                    badge: counts?.accounts.pending ?? 0,
                },
                {
                    title: 'Provision Approvers',
                    href: approvers.index(),
                },
                {
                    title: 'Officer Change Requests',
                    href: adminOfficerChangeRequests.index(),
                    badge: counts?.accounts.officerChanges ?? 0,
                },
            ],
        });
    }

    if (adviserRole?.organization_id) {
        workspaceRows.push({
            title: 'Manage Officers',
            href: officers.index({ organization: adviserRole.organization_id }),
            icon: Users,
        });
    }

    if (isSdao) {
        workspaceRows.push(
            {
                title: 'Organizations',
                href: adminOrganizations.index(),
                icon: Building2,
            },
            {
                title: 'Document Archive',
                href: archive.index(),
                icon: Archive,
            },
            {
                title: 'Activity Log',
                href: activityLog.index(),
                icon: History,
            },
            {
                title: 'Current Period',
                href: currentPeriodSettings.edit(),
                icon: CalendarCog,
            },
        );
    }

    const sections: NavSection[] = [
        { label: 'Platform', entries: platformItems },
    ];

    const workspace = [...workspaceEntries, ...workspaceRows];

    if (workspace.length > 0) {
        sections.push({ label: 'Workspace', entries: workspace });
    }

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
