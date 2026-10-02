import { Deferred, Head, Link, usePage } from '@inertiajs/react';
import { ChevronRight, CircleCheck, History, TriangleAlert } from 'lucide-react';
import PageHeader from '@/components/page-header';
import PageNotice from '@/components/page-notice';
import ProposalFunnelCard from '@/components/proposal-funnel-chart';
import type { FunnelVariant } from '@/components/proposal-funnel-chart';
import { RelativeTime } from '@/components/relative-time';
import ReturnAnalyticsRow, { ReturnAnalyticsSkeleton } from '@/components/return-analytics-row';
import type { ReturnAnalytics } from '@/components/return-analytics-row';
import StatTile from '@/components/stat-tile';
import { ActionBadge, StatusBadge } from '@/components/status-badge';
import StatusDistributionCard from '@/components/status-distribution-card';
import type { StatusCount } from '@/components/status-distribution-card';
import type { StuckByApprover } from '@/components/stuck-by-approver-card';
import StuckByApproverCard from '@/components/stuck-by-approver-card';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
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
import type { WaitingSplit } from '@/components/waiting-split-card';
import WaitingSplitCard from '@/components/waiting-split-card';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import * as activityLog from '@/routes/admin/activity';

type UpcomingAlert = { count: number; names: string[]; href: string };

type Tile = {
    key: string;
    label: string;
    count: number;
    href: string;
    hint: string;
    hintTone: 'muted' | 'warning' | 'destructive';
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
    upcomingAlert: UpcomingAlert | null;
    tiles: Tile[];
    stuckByApprover: StuckByApprover;
    waitingSplit: WaitingSplit;
    statusDistribution: StatusCount[];
    proposalFunnel: FunnelVariant[];
    returnAnalytics?: ReturnAnalytics;
    recentActivity: ActivityEntry[];
    oldestInReview: AgingDocument[];
    orgCompliance: { pending: OrgWithPending[]; notRenewed: OrgNotRenewed[] };
};

/** Fixed width so every row's timestamp lands in the same column regardless of label length ("just now" vs. "15d ago"). */
const TIMESTAMP_COLUMN_CLASS = 'w-20 shrink-0 text-right text-sm text-muted-foreground tabular-nums';

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
    upcomingAlert,
    tiles,
    stuckByApprover,
    waitingSplit,
    statusDistribution,
    proposalFunnel,
    returnAnalytics,
    recentActivity,
    oldestInReview,
    orgCompliance,
}: Props) {
    const { currentPeriod } = usePage().props;
    const academicYear = currentPeriod.academic_year;
    const getInitials = useInitials();

    return (
        <>
            <Head title="Admin Dashboard" />

            <div className="space-y-6">
                <PageHeader
                    title="Admin Dashboard"
                    subtitle="Everything happening across SDAO this academic year"
                />

                {upcomingAlert && (
                    <PageNotice
                        tone="down"
                        icon={TriangleAlert}
                        className="border-destructive/40 bg-destructive/10"
                        action={
                            <Link
                                href={upcomingAlert.href}
                                className="shrink-0 text-sm font-medium text-destructive-foreground hover:underline"
                            >
                                Open these {upcomingAlert.count}
                            </Link>
                        }
                    >
                        <strong className="font-semibold text-destructive-foreground">
                            {upcomingAlert.count === 1
                                ? '1 activity happens within 7 days and is still not approved.'
                                : `${upcomingAlert.count} activities happen within 7 days and are still not approved.`}
                        </strong>{' '}
                        <span className="text-muted-foreground">
                            {upcomingAlert.names.join(', ')}
                        </span>
                    </PageNotice>
                )}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5 sm:max-lg:[&>*:last-child]:col-span-2">
                    {tiles.map((tile) => (
                        <StatTile
                            key={tile.key}
                            label={tile.label}
                            count={tile.count}
                            href={tile.href}
                            hint={tile.hint}
                            hintTone={tile.hintTone}
                            tone="neutral"
                            valueClassName="text-3xl"
                            labelClassName="lg:min-h-[2lh]"
                        />
                    ))}
                </div>

                <div className="grid gap-4 lg:grid-cols-3 [&>*]:min-w-0">
                    <div className="lg:col-span-2 [&>*]:h-full">
                        <StuckByApproverCard data={stuckByApprover} />
                    </div>
                    <WaitingSplitCard data={waitingSplit} />
                </div>

                <div className="grid gap-4 md:grid-cols-2 [&>*]:min-w-0">
                    <StatusDistributionCard data={statusDistribution} />
                    <ProposalFunnelCard funnels={proposalFunnel} />
                </div>

                <Deferred data="returnAnalytics" fallback={<ReturnAnalyticsSkeleton />}>
                    {returnAnalytics ? <ReturnAnalyticsRow data={returnAnalytics} /> : <ReturnAnalyticsSkeleton />}
                </Deferred>

                <div className="grid gap-4 md:grid-cols-2 [&>*]:min-w-0">
                    <Card>
                        <CardHeader className="flex flex-row items-start justify-between gap-2">
                            <CardTitle className="text-base">
                                Recent Activity
                            </CardTitle>
                            <Link
                                href={activityLog.index()}
                                className="inline-flex items-center gap-1 text-sm text-primary-text hover:underline"
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
                                                    className="block sm:truncate max-sm:break-words text-sm font-semibold hover:underline"
                                                >
                                                    {entry.documentTitle}
                                                </Link>
                                                {/* Actor byline — same avatar treatment (shape, fallback colors) as the account menu at the bottom of the sidebar (see UserInfo), just sized down for this denser list. */}
                                                <div className="mt-1 flex items-center gap-1.5">
                                                    <Avatar className="size-5 overflow-hidden rounded-full">
                                                        <AvatarFallback className="rounded-full bg-neutral-200 text-[0.625rem] text-black dark:bg-neutral-700 dark:text-white">
                                                            {getInitials(entry.actorName)}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <span className="sm:truncate max-sm:break-words text-sm text-muted-foreground">
                                                        {entry.actorName}
                                                    </span>
                                                </div>
                                                <div className="mt-1.5 flex flex-wrap items-center gap-2">
                                                    <ActionBadge
                                                        action={entry.action}
                                                    />
                                                    <span className="sm:truncate max-sm:break-words rounded-md bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                                                        {entry.organizationName}
                                                    </span>
                                                </div>
                                            </div>
                                            <span className={TIMESTAMP_COLUMN_CLASS}>
                                                <RelativeTime dateString={entry.createdAt} />
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
                                                    className="block sm:truncate max-sm:break-words text-sm font-semibold hover:underline"
                                                >
                                                    {doc.title}
                                                </Link>
                                                <p className="mt-1 sm:truncate max-sm:break-words text-sm text-muted-foreground">
                                                    {doc.formTypeLabel}
                                                    {doc.stepLabel &&
                                                        ` · ${doc.stepLabel}`}
                                                </p>
                                                <div className="mt-1.5 flex flex-wrap items-center gap-2">
                                                    {/* Every row here is, by this widget's own definition, in review — not fetched data, just the constant this section is scoped to. */}
                                                    <StatusBadge status="in_review" />
                                                    <span className="sm:truncate max-sm:break-words rounded-md bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
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

                <div className="grid gap-4 md:grid-cols-2 [&>*]:min-w-0">
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
