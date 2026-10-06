import { Deferred, Head, Link, usePage } from '@inertiajs/react';
import { Hourglass, Inbox, Undo2, UserRoundSearch, UserX } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import OldestInReviewCard from '@/components/oldest-in-review-card';
import type { OldestDocument } from '@/components/oldest-in-review-card';
import OrgComplianceCard, { academicYearLabel } from '@/components/org-compliance-card';
import type { OrgCompliance } from '@/components/org-compliance-card';
import PageHeader from '@/components/page-header';
import PageNotice from '@/components/page-notice';
import ProposalFunnelCard from '@/components/proposal-funnel-chart';
import type { FunnelVariant } from '@/components/proposal-funnel-chart';
import RecentActivityCard from '@/components/recent-activity-card';
import type { ActivityEntry } from '@/components/recent-activity-card';
import ReturnAnalyticsRow, { ReturnAnalyticsSkeleton } from '@/components/return-analytics-row';
import type { ReturnAnalytics } from '@/components/return-analytics-row';
import type { StatIconTileTone } from '@/components/stat-icon-tile';
import StatTile from '@/components/stat-tile';
import StatusDistributionCard from '@/components/status-distribution-card';
import type { StatusCount } from '@/components/status-distribution-card';
import StuckByApproverCard from '@/components/stuck-by-approver-card';
import type { StuckByApprover } from '@/components/stuck-by-approver-card';
import WaitingSplitCard from '@/components/waiting-split-card';
import type { WaitingSplit } from '@/components/waiting-split-card';
import WeeklySubmissionsCard, { WeeklySubmissionsSkeleton } from '@/components/weekly-submissions-card';
import type { WeeklySubmissions } from '@/components/weekly-submissions-card';
import * as activityLog from '@/routes/admin/activity';
import * as stuckDocuments from '@/routes/admin/stuck-documents';

/** One icon per attention tile, reusing the icons the other admin pages already use for the same idea. */
const TILE_ICONS: Record<string, LucideIcon> = {
    awaiting_sdao: Inbox,
    stuck_with_approvers: Hourglass,
    returned: Undo2,
    pending_accounts: UserRoundSearch,
    without_adviser: UserX,
};

const TILE_TONES: Record<Tile['hintTone'], StatIconTileTone> = {
    muted: 'default',
    warning: 'warning',
    destructive: 'alert',
};

type UpcomingAlert = { count: number; names: string[]; href: string };

type Tile = {
    key: string;
    label: string;
    count: number;
    href: string;
    hint: string;
    hintTone: 'muted' | 'warning' | 'destructive';
};

type Props = {
    upcomingAlert: UpcomingAlert | null;
    tiles: Tile[];
    stuckByApprover: StuckByApprover;
    waitingSplit: WaitingSplit;
    statusDistribution: StatusCount[];
    proposalFunnel: FunnelVariant[];
    returnAnalytics?: ReturnAnalytics;
    recentActivity: ActivityEntry[];
    oldestInReview: OldestDocument[];
    weeklySubmissions?: WeeklySubmissions;
    orgCompliance: OrgCompliance;
};

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
    weeklySubmissions,
    orgCompliance,
}: Props) {
    const { currentPeriod } = usePage().props;

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
                        tone="destructive"
                        urgent
                        title={
                            upcomingAlert.count === 1
                                ? '1 activity happens within 7 days and is still not approved.'
                                : `${upcomingAlert.count} activities happen within 7 days and are still not approved.`
                        }
                        action={
                            <Link
                                href={upcomingAlert.href}
                                className="text-sm font-medium text-destructive-foreground hover:underline"
                            >
                                Open these {upcomingAlert.count}
                            </Link>
                        }
                    >
                        {upcomingAlert.names.join(', ')}
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
                            tileIcon={TILE_ICONS[tile.key]}
                            tileTone={TILE_TONES[tile.hintTone]}
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
                    {returnAnalytics ? (
                        <ReturnAnalyticsRow data={returnAnalytics} />
                    ) : (
                        <ReturnAnalyticsSkeleton />
                    )}
                </Deferred>

                <div className="grid gap-4 md:grid-cols-2 [&>*]:min-w-0">
                    <RecentActivityCard entries={recentActivity} viewAllHref={activityLog.index().url} />
                    <OldestInReviewCard
                        documents={oldestInReview}
                        viewAllHref={stuckDocuments.index({ query: { waiting_on: 'approver' } }).url}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-3 [&>*]:min-w-0">
                    <div className="lg:col-span-2 [&>*]:h-full">
                        <Deferred data="weeklySubmissions" fallback={<WeeklySubmissionsSkeleton />}>
                            {weeklySubmissions ? (
                                <WeeklySubmissionsCard data={weeklySubmissions} />
                            ) : (
                                <WeeklySubmissionsSkeleton />
                            )}
                        </Deferred>
                    </div>
                    <OrgComplianceCard data={orgCompliance} />
                </div>

                <p className="text-xs text-muted-foreground">
                    Showing A.Y. {academicYearLabel(currentPeriod.academic_year)}. Idle time is measured from the
                    latest entry in the document transition log.
                </p>
            </div>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [{ title: 'Admin' }, { title: 'Dashboard' }],
};
