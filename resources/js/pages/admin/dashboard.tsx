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
import StatTile from '@/components/stat-tile';
import type { WeeklyDelta } from '@/components/stat-tile';
import { ActionBadge } from '@/components/status-badge';
import StatusDistributionBar from '@/components/status-distribution-bar';
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
import { formatRelativeTime, scaleToPercent } from '@/lib/utils';
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
    const statusTotal = statusDistribution.reduce((sum, s) => sum + s.count, 0);
    const proposalTotal = proposalFunnel.reduce((sum, g) => sum + g.total, 0);
    // Scaled against a max shared across EVERY group's steps, not each
    // group's own local peak — otherwise the tallest step in every group
    // always renders at 100%, and a chain variant with 1 proposal looks
    // identical to one with 15. See scaleToPercent's docblock.
    const globalMaxStep = Math.max(
        ...proposalFunnel.flatMap((g) => g.steps.map((s) => s.count)),
        1,
    );

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
                        <CardContent>
                            <StatusDistributionBar data={statusDistribution} />
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
                            <div className="space-y-6">
                                {proposalFunnel.map((group) => (
                                    <div key={group.variant}>
                                        <p className="text-sm font-medium">
                                            {group.label}{' '}
                                            <span className="text-muted-foreground">
                                                · {group.total}
                                            </span>
                                        </p>
                                        <div className="mt-2 space-y-1.5">
                                            {group.steps.map((step) => (
                                                <div
                                                    key={step.role}
                                                    className="flex items-center gap-3"
                                                >
                                                    <span className="w-40 shrink-0 truncate text-sm font-medium text-foreground/80">
                                                        {step.role}
                                                    </span>
                                                    <div className="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                                                        <div
                                                            className="h-2 rounded-full bg-info"
                                                            style={{
                                                                width: `${scaleToPercent(step.count, globalMaxStep)}%`,
                                                            }}
                                                        />
                                                    </div>
                                                    <span className="w-6 shrink-0 text-right text-sm tabular-nums">
                                                        {step.count}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    )}
                </Card>

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
                                            className="flex items-center justify-between gap-4 py-2.5 first:pt-0 last:pb-0"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <Link
                                                    href={entry.href}
                                                    className="truncate text-sm font-medium hover:underline"
                                                >
                                                    {entry.documentTitle}
                                                </Link>
                                                <div className="mt-1 flex flex-wrap items-center gap-2">
                                                    <span className="truncate text-sm font-medium text-foreground">
                                                        {entry.actorName}
                                                    </span>
                                                    <ActionBadge
                                                        action={entry.action}
                                                    />
                                                    <span className="truncate rounded-md bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                                                        {entry.organizationName}
                                                    </span>
                                                </div>
                                            </div>
                                            <span className="shrink-0 text-sm text-muted-foreground">
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
                                            className="flex items-center justify-between gap-4 py-2.5 first:pt-0 last:pb-0"
                                        >
                                            <div className="min-w-0">
                                                <Link
                                                    href={doc.href}
                                                    className="truncate text-sm font-medium hover:underline"
                                                >
                                                    {doc.title}
                                                </Link>
                                                <p className="truncate text-sm text-muted-foreground">
                                                    {doc.organizationName} ·{' '}
                                                    {doc.formTypeLabel}
                                                    {doc.stepLabel &&
                                                        ` · ${doc.stepLabel}`}
                                                </p>
                                            </div>
                                            <span className="shrink-0 text-sm text-muted-foreground tabular-nums">
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
                                            <Badge variant="secondary">
                                                {org.count}
                                            </Badge>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Not Yet Renewed This Year
                            </CardTitle>
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
