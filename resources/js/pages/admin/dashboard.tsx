import { Head, Link, usePage } from '@inertiajs/react';
import {
    ChevronRight,
    CircleCheck,
    FileText,
    History,
    Inbox,
    UserCheck,
    UserPlus,
} from 'lucide-react';
import ProposalFunnelChart from '@/components/proposal-funnel-chart';
import StatTile from '@/components/stat-tile';
import type { WeeklyDelta } from '@/components/stat-tile';
import { ActionBadge, StatusBadge } from '@/components/status-badge';
import StatusDistributionPie from '@/components/status-distribution-pie';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { useInitials } from '@/hooks/use-initials';
import { cn, formatRelativeTime } from '@/lib/utils';
import * as activityLog from '@/routes/admin/activity';

type QuickStat = {
    label: string;
    count: number;
    href: string;
    weekly?: WeeklyDelta;
    urgent?: boolean;
};

type StatusCount = { status: string; count: number };

type FunnelStep = { role: string; count: number };

type FunnelGroup = {
    variant: string;
    label: string;
    total: number;
    steps: FunnelStep[];
};

type ActivityEntry = {
    id: number;
    actorName: string;
    action: string;
    documentTitle: string;
    organizationName: string;
    createdAt: string;
    href: string;
};

type AgingDocument = {
    id: number;
    title: string;
    organizationName: string;
    formTypeLabel: string;
    stepLabel: string | null;
    daysSinceActivity: number;
    href: string;
};

type OrgWithPending = {
    organizationId: number;
    organizationName: string;
    count: number;
};
type OrgNotRenewed = { organizationId: number; organizationName: string };

type Props = {
    quickStats: QuickStat[];
    weeklyVolume: { thisWeek: number; lastWeek: number; delta: number };
    statusDistribution: StatusCount[];
    proposalFunnel: FunnelGroup[];
    recentActivity: ActivityEntry[];
    oldestInReview: AgingDocument[];
    orgCompliance: { pending: OrgWithPending[]; notRenewed: OrgNotRenewed[] };
};

const QUICK_STAT_ICONS = [Inbox, FileText, UserCheck, UserPlus];

/** Fixed width so every row's timestamp lands in the same column regardless of label length ("just now" vs. "15d ago"). */
const TIMESTAMP_COLUMN_CLASS = 'w-20 shrink-0 text-right text-sm text-muted-foreground tabular-nums';

/**
 * "No submissions yet this week" / "12 submissions this week, up 4 from
 * last week" / "…, down 2 from last week" / "…, same as last week" — plain
 * language, no ± notation. `academicYear` used to be shown right above this
 * ("Showing: 2026-2027") but that's now persistent navbar context (see
 * app-sidebar-header.tsx) instead of body text repeated on every visit.
 */
function weeklyVolumeCopy(thisWeek: number, delta: number): string {
    if (thisWeek === 0) {
        return 'No submissions yet this week';
    }

    const noun = `submission${thisWeek === 1 ? '' : 's'}`;

    if (delta > 0) {
        return `${thisWeek} ${noun} this week, up ${delta} from last week`;
    }

    if (delta < 0) {
        return `${thisWeek} ${noun} this week, down ${Math.abs(delta)} from last week`;
    }

    return `${thisWeek} ${noun} this week, same as last week`;
}

/**
 * Solid warning/destructive chips, reusing the exact tokens Returned/
 * Rejected already use elsewhere on this dashboard — a count badge should
 * carry the same urgency language as a status badge, not sit there as a
 * decorative gray number. Thresholds are a judgment call (not derived data):
 * a couple of pending items is routine queue depth, several is a forming
 * backlog, many is genuinely stuck.
 */
function pendingCountBadgeClass(count: number): string | undefined {
    if (count >= 5) {
        return 'border-transparent bg-destructive text-white';
    }

    if (count >= 3) {
        return 'border-transparent bg-warning text-background';
    }

    return undefined;
}

/**
 * Same idea as `pendingCountBadgeClass`, tuned for a slower-moving
 * compliance metric (total orgs not yet renewed) rather than a per-org
 * pending-document count, so the thresholds sit higher.
 */
function notRenewedCountBadgeClass(count: number): string | undefined {
    if (count >= 8) {
        return 'border-transparent bg-destructive text-white';
    }

    if (count >= 4) {
        return 'border-transparent bg-warning text-background';
    }

    return undefined;
}

export default function AdminDashboard({
    quickStats,
    weeklyVolume,
    statusDistribution,
    proposalFunnel,
    recentActivity,
    oldestInReview,
    orgCompliance,
}: Props) {
    const { academicYear } = usePage().props;
    const getInitials = useInitials();
    const statusTotal = statusDistribution.reduce((sum, s) => sum + s.count, 0);
    const proposalTotal = proposalFunnel.reduce((sum, g) => sum + g.total, 0);

    return (
        <>
            <Head title="Admin Dashboard" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-balance">
                        Admin Dashboard
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {weeklyVolumeCopy(
                            weeklyVolume.thisWeek,
                            weeklyVolume.delta,
                        )}
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {quickStats.map((stat, index) => (
                        <StatTile
                            key={stat.label}
                            label={stat.label}
                            count={stat.count}
                            href={stat.href}
                            icon={QUICK_STAT_ICONS[index]}
                            weekly={stat.weekly}
                            urgent={stat.urgent}
                        />
                    ))}
                </div>

                {/* Default grid stretch (items-stretch): both cards match
                    the row's tallest sibling instead of each sizing to its
                    own content — the pie card's CardContent centers its
                    (shorter) content vertically to fill that extra height
                    gracefully rather than leaving it top-anchored. */}
                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Status Distribution
                            </CardTitle>
                            <CardDescription>
                                {statusTotal > 0
                                    ? `${statusTotal} documents in ${academicYear}`
                                    : 'No documents in this academic year yet.'}
                            </CardDescription>
                        </CardHeader>
                        {statusTotal > 0 && (
                            <CardContent className="flex flex-1 items-center">
                                <StatusDistributionPie data={statusDistribution} />
                            </CardContent>
                        )}
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Activity Proposal Funnel
                            </CardTitle>
                            <CardDescription>
                                {proposalFunnel.length > 0
                                    ? `${proposalTotal} proposals in review across ${proposalFunnel.length} chain variant${proposalFunnel.length === 1 ? '' : 's'}`
                                    : 'No activity proposals are currently in review.'}
                            </CardDescription>
                        </CardHeader>
                        {proposalFunnel.length > 0 && (
                            <CardContent>
                                <ProposalFunnelChart groups={proposalFunnel} />
                            </CardContent>
                        )}
                    </Card>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader className="flex flex-row items-start justify-between gap-2">
                            <CardTitle className="text-base">
                                Recent Activity
                            </CardTitle>
                            <Link
                                href={activityLog.index()}
                                className="inline-flex items-center gap-1 text-sm text-primary hover:underline"
                            >
                                View all activity
                                <ChevronRight className="size-3.5" />
                            </Link>
                        </CardHeader>
                        <CardContent>
                            {recentActivity.length === 0 ? (
                                <Empty className="gap-4 p-6">
                                    <EmptyHeader>
                                        <EmptyMedia
                                            variant="icon"
                                            className="size-8 [&_svg]:size-5"
                                        >
                                            <History />
                                        </EmptyMedia>
                                        <EmptyTitle>
                                            Nothing has happened yet
                                        </EmptyTitle>
                                        <EmptyDescription>
                                            Submissions and approvals will show
                                            up here as they happen.
                                        </EmptyDescription>
                                    </EmptyHeader>
                                </Empty>
                            ) : (
                                <div className="divide-y">
                                    {recentActivity.map((entry) => (
                                        <div
                                            key={entry.id}
                                            className="flex items-start gap-3 py-2.5 first:pt-0 last:pb-0"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <Link
                                                    href={entry.href}
                                                    className="block truncate text-sm font-semibold hover:underline"
                                                >
                                                    {entry.documentTitle}
                                                </Link>
                                                {/* Actor byline — same avatar treatment (shape, fallback colors) as the account menu at the bottom of the sidebar (see UserInfo), just sized down for this denser list. */}
                                                <div className="mt-1 flex items-center gap-1.5">
                                                    <Avatar className="size-5 overflow-hidden rounded-full">
                                                        <AvatarFallback className="rounded-full bg-neutral-200 text-[10px] text-black dark:bg-neutral-700 dark:text-white">
                                                            {getInitials(entry.actorName)}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <span className="truncate text-sm text-muted-foreground">
                                                        {entry.actorName}
                                                    </span>
                                                </div>
                                                <div className="mt-1.5 flex flex-wrap items-center gap-2">
                                                    <ActionBadge
                                                        action={entry.action}
                                                    />
                                                    <span className="truncate rounded-md bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                                                        {entry.organizationName}
                                                    </span>
                                                </div>
                                            </div>
                                            <span className={TIMESTAMP_COLUMN_CLASS}>
                                                {formatRelativeTime(
                                                    entry.createdAt,
                                                )}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Oldest In-Review Documents
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {oldestInReview.length === 0 ? (
                                <Empty className="gap-4 p-6">
                                    <EmptyHeader>
                                        <EmptyMedia
                                            variant="icon"
                                            className="size-8 [&_svg]:size-5"
                                        >
                                            <CircleCheck />
                                        </EmptyMedia>
                                        <EmptyTitle>
                                            Nothing is sitting idle
                                        </EmptyTitle>
                                        <EmptyDescription>
                                            Every in-review document has had
                                            recent activity.
                                        </EmptyDescription>
                                    </EmptyHeader>
                                </Empty>
                            ) : (
                                <div className="divide-y">
                                    {oldestInReview.map((doc) => (
                                        <div
                                            key={doc.id}
                                            className="flex items-start gap-3 py-2.5 first:pt-0 last:pb-0"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <Link
                                                    href={doc.href}
                                                    className="block truncate text-sm font-semibold hover:underline"
                                                >
                                                    {doc.title}
                                                </Link>
                                                <p className="mt-1 truncate text-sm text-muted-foreground">
                                                    {doc.formTypeLabel}
                                                    {doc.stepLabel &&
                                                        ` · ${doc.stepLabel}`}
                                                </p>
                                                <div className="mt-1.5 flex flex-wrap items-center gap-2">
                                                    {/* Every row here is, by this widget's own definition, in review — not fetched data, just the constant this section is scoped to. */}
                                                    <StatusBadge status="in_review" />
                                                    <span className="truncate rounded-md bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                                                        {doc.organizationName}
                                                    </span>
                                                </div>
                                            </div>
                                            <span className={TIMESTAMP_COLUMN_CLASS}>
                                                {doc.daysSinceActivity}d ago
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Organizations With Pending Items
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {orgCompliance.pending.length === 0 ? (
                                <Empty className="gap-4 p-6">
                                    <EmptyHeader>
                                        <EmptyMedia
                                            variant="icon"
                                            className="size-8 [&_svg]:size-5"
                                        >
                                            <CircleCheck />
                                        </EmptyMedia>
                                        <EmptyTitle>Nothing pending</EmptyTitle>
                                        <EmptyDescription>
                                            No organization has a draft,
                                            in-review, or returned document
                                            right now.
                                        </EmptyDescription>
                                    </EmptyHeader>
                                </Empty>
                            ) : (
                                <div className="divide-y">
                                    {orgCompliance.pending.map((org) => (
                                        <div
                                            key={org.organizationId}
                                            className="flex items-center justify-between gap-2 py-2.5 first:pt-0 last:pb-0"
                                        >
                                            <span className="text-sm font-medium">
                                                {org.organizationName}
                                            </span>
                                            <Badge
                                                variant="outline"
                                                className={cn(
                                                    !pendingCountBadgeClass(org.count) &&
                                                        'border-transparent bg-secondary text-secondary-foreground',
                                                    pendingCountBadgeClass(org.count),
                                                )}
                                            >
                                                {org.count}
                                            </Badge>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between gap-2">
                            <CardTitle className="text-base">
                                Not Yet Renewed This Year
                            </CardTitle>
                            {orgCompliance.notRenewed.length > 0 && (
                                <Badge
                                    variant="outline"
                                    className={cn(
                                        !notRenewedCountBadgeClass(
                                            orgCompliance.notRenewed.length,
                                        ) &&
                                            'border-transparent bg-secondary text-secondary-foreground',
                                        notRenewedCountBadgeClass(
                                            orgCompliance.notRenewed.length,
                                        ),
                                    )}
                                >
                                    {orgCompliance.notRenewed.length}
                                </Badge>
                            )}
                        </CardHeader>
                        <CardContent>
                            {orgCompliance.notRenewed.length === 0 ? (
                                <Empty className="gap-4 p-6">
                                    <EmptyHeader>
                                        <EmptyMedia
                                            variant="icon"
                                            className="size-8 [&_svg]:size-5"
                                        >
                                            <CircleCheck />
                                        </EmptyMedia>
                                        <EmptyTitle>All caught up</EmptyTitle>
                                        <EmptyDescription>
                                            Every organization has renewed for{' '}
                                            {academicYear}.
                                        </EmptyDescription>
                                    </EmptyHeader>
                                </Empty>
                            ) : (
                                <>
                                    <div className="divide-y">
                                        {orgCompliance.notRenewed.map((org) => (
                                            <div
                                                key={org.organizationId}
                                                className="py-2.5 text-sm font-medium first:pt-0 last:pb-0"
                                            >
                                                {org.organizationName}
                                            </div>
                                        ))}
                                    </div>
                                    <p className="mt-3 text-xs text-muted-foreground">
                                        Organizations founded this academic year
                                        may not need to renew yet — this list
                                        isn&apos;t filtered for that.
                                    </p>
                                </>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [{ title: 'Admin' }, { title: 'Dashboard' }],
};
