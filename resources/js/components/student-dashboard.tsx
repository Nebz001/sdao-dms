import { Link, usePage } from '@inertiajs/react';
import {
    AlarmClock,
    Ban,
    CalendarClock,
    CalendarDays,
    ChevronRight,
    CircleCheck,
    ClipboardCheck,
    FilePlus2,
    FileText,
    Inbox,
    RotateCcw,
    Timer,
} from 'lucide-react';
import DocumentStepTracker from '@/components/document-step-tracker';
import type { TrackerStep } from '@/components/document-step-tracker';
import FormTypeBadge from '@/components/form-type-badge';
import { NotificationRow } from '@/components/notification-row';
import { RelativeTime } from '@/components/relative-time';
import RequirementsChecklist from '@/components/requirements-checklist';
import type { RequirementsData } from '@/components/requirements-checklist';
import StatTile from '@/components/stat-tile';
import { OrganizationStatusBadge, StatusBadge } from '@/components/status-badge';
import SubmissionsChart from '@/components/submissions-chart';
import type { SubmissionMonth } from '@/components/submissions-chart';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { useNotificationRead } from '@/hooks/use-notification-read';

type Href = { count: number; href: string };

export type StudentDashboardMeta = {
    organizationName: string;
    organizationStatus: string;
    academicYear: string;
    termLabel: string;
    historyHref: string;
};

export type StudentKpis = {
    totalSubmitted: Href;
    inReview: Href;
    needsRevision: Href;
    approved: Href;
    averageTimeToDecision: {
        hours: number | null;
        sampleSize: number;
        href: string;
    };
};

export type NeedsActionItem = {
    id: string;
    kind: string;
    title: string;
    formTypeLabel: string;
    comment: string | null;
    returnedByName: string | null;
    returnedAt: string | null;
    flaggedCount: number;
    actionLabel: string;
    actionHref: string;
};

export type NeedsActionData = { total: number; items: NeedsActionItem[] };

export type TrackerItem = {
    id: number;
    title: string;
    formTypeLabel: string;
    status: string;
    waitingSince: string | null;
    href: string;
    steps: TrackerStep[];
};

export type TrackerData = { total: number; items: TrackerItem[] };

export type QuickSubmitTile = {
    formType: string;
    label: string;
    href: string;
    enabled: boolean;
    reason: string | null;
};

export type UpcomingActivity = {
    id: number;
    title: string;
    venue: string;
    date: string;
    startTime: string;
    endTime: string;
    status: string;
    href: string;
};

export type ReportDue = {
    id: number;
    title: string;
    date: string;
    daysSince: number;
    fileHref: string;
};

export type UpcomingData = {
    upcoming: UpcomingActivity[];
    reportsDue: ReportDue[];
};

type StudentDashboardProps = {
    meta: StudentDashboardMeta;
    needsAction: NeedsActionData;
    tracker: TrackerData;
    requirements: RequirementsData;
    kpis: StudentKpis;
    quickSubmit: QuickSubmitTile[];
    upcoming: UpcomingData;
    submissions: SubmissionMonth[];
};

const QUICK_SUBMIT_ICON: Record<string, typeof FilePlus2> = {
    organization_registration: FilePlus2,
    organization_renewal: RotateCcw,
    activity_calendar: CalendarDays,
    activity_proposal: FileText,
    after_activity_report: ClipboardCheck,
};

/** "18 min" under an hour, "6 hrs" under 2 days, otherwise "2.4 days" — mirrors ApproverDashboard's formatReviewDuration(). */
function formatDuration(hours: number | null): string {
    if (hours === null) {
        return '—';
    }

    if (hours < 1) {
        return `${Math.round(hours * 60)} min`;
    }

    if (hours < 48) {
        return `${Math.round(hours)} hrs`;
    }

    return `${(hours / 24).toFixed(1)} days`;
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}

function formatTime(time: string): string {
    const [hours, minutes] = time.split(':').map(Number);
    const period = hours >= 12 ? 'PM' : 'AM';
    const displayHour = hours % 12 === 0 ? 12 : hours % 12;

    return `${displayHour}:${String(minutes).padStart(2, '0')} ${period}`;
}

export default function StudentDashboard({
    meta,
    needsAction,
    tracker,
    requirements,
    kpis,
    quickSubmit,
    upcoming,
    submissions,
}: StudentDashboardProps) {
    const { notifications } = usePage().props;
    const { markRowRead, visitNotification } = useNotificationRead();
    const recentNotifications = (notifications?.items ?? []).slice(0, 5);

    const returnedItems = needsAction.items.filter(
        (item) => item.kind === 'returned',
    );
    const draftItems = needsAction.items.filter(
        (item) => item.kind === 'draft',
    );

    return (
        <div className="flex flex-col gap-4">
            {/* Header line. */}
            <Card>
                <CardContent className="flex flex-wrap items-center justify-between gap-3 py-4">
                    <div className="flex items-center gap-3">
                        <div>
                            <p className="text-lg font-semibold">
                                {meta.organizationName}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {meta.termLabel} · {meta.academicYear}
                            </p>
                        </div>
                        <OrganizationStatusBadge
                            status={meta.organizationStatus}
                        />
                    </div>
                    <Link
                        href={meta.historyHref}
                        className="inline-flex shrink-0 items-center gap-1 text-sm text-primary-text hover:underline"
                    >
                        View document history
                        <ChevronRight className="size-3.5" />
                    </Link>
                </CardContent>
            </Card>

            {/* Needs Your Action. */}
            <Card>
                <CardHeader className="flex flex-row items-start justify-between gap-2">
                    <div>
                        <CardTitle className="text-base">
                            Needs Your Action
                        </CardTitle>
                        <CardDescription>
                            {needsAction.total === 0
                                ? 'Nothing needs your attention right now.'
                                : `${needsAction.total} document${needsAction.total === 1 ? '' : 's'} waiting on you.`}
                        </CardDescription>
                    </div>
                    {needsAction.total > needsAction.items.length && (
                        <Link
                            href={meta.historyHref}
                            className="inline-flex shrink-0 items-center gap-1 text-sm text-primary-text hover:underline"
                        >
                            See all
                            <ChevronRight className="size-3.5" />
                        </Link>
                    )}
                </CardHeader>
                <CardContent>
                    {needsAction.total === 0 ? (
                        <Empty className="gap-4 p-6">
                            <EmptyHeader>
                                <EmptyMedia
                                    variant="icon"
                                    className="size-8 [&_svg]:size-5"
                                >
                                    <CircleCheck />
                                </EmptyMedia>
                                <EmptyTitle>You&apos;re all caught up</EmptyTitle>
                                <EmptyDescription>
                                    Returned documents and unfinished drafts
                                    will show up here.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <div className="flex flex-col gap-4">
                            {returnedItems.length > 0 && (
                                <div className="divide-y">
                                    {returnedItems.map((item) => (
                                        <div
                                            key={item.id}
                                            className="flex flex-wrap items-start justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <FormTypeBadge
                                                        label={
                                                            item.formTypeLabel
                                                        }
                                                    />
                                                    {item.flaggedCount > 0 && (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-transparent bg-warning/10 text-[10px] text-warning"
                                                        >
                                                            {item.flaggedCount}{' '}
                                                            section
                                                            {item.flaggedCount === 1
                                                                ? ''
                                                                : 's'}{' '}
                                                            flagged
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="mt-1 truncate text-sm font-semibold">
                                                    {item.title}
                                                </p>
                                                {item.comment && (
                                                    <p className="mt-0.5 truncate text-sm text-muted-foreground">
                                                        &ldquo;{item.comment}
                                                        &rdquo;
                                                    </p>
                                                )}
                                                <p className="mt-0.5 text-xs text-muted-foreground">
                                                    {item.returnedByName ??
                                                        'An approver'}
                                                    {item.returnedAt && (
                                                        <>
                                                            {' '}
                                                            ·{' '}
                                                            <RelativeTime
                                                                dateString={
                                                                    item.returnedAt
                                                                }
                                                            />
                                                        </>
                                                    )}
                                                </p>
                                            </div>
                                            <Button asChild size="sm">
                                                <Link href={item.actionHref}>
                                                    {item.actionLabel}
                                                </Link>
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            )}

                            {draftItems.length > 0 && (
                                <div>
                                    <p className="mb-1.5 text-xs font-medium text-muted-foreground">
                                        Continue your drafts
                                    </p>
                                    <div className="divide-y rounded-md border border-dashed">
                                        {draftItems.map((item) => (
                                            <div
                                                key={item.id}
                                                className="flex items-center justify-between gap-3 px-3 py-2"
                                            >
                                                <p className="truncate text-sm text-muted-foreground">
                                                    {item.title}
                                                </p>
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link
                                                        href={item.actionHref}
                                                    >
                                                        {item.actionLabel}
                                                    </Link>
                                                </Button>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Tracker (8 cols) + Checklist (4 cols). */}
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                <Card className="lg:col-span-8">
                    <CardHeader className="flex flex-row items-start justify-between gap-2">
                        <div>
                            <CardTitle className="text-base">
                                In Progress
                            </CardTitle>
                            <CardDescription>
                                {tracker.items.length === 0
                                    ? 'Nothing is currently in review.'
                                    : `${tracker.items.length} document${tracker.items.length === 1 ? '' : 's'} moving through the approval chain.`}
                            </CardDescription>
                        </div>
                        {tracker.total > tracker.items.length && (
                            <Link
                                href={meta.historyHref}
                                className="inline-flex shrink-0 items-center gap-1 text-sm text-primary-text hover:underline"
                            >
                                See all
                                <ChevronRight className="size-3.5" />
                            </Link>
                        )}
                    </CardHeader>
                    <CardContent>
                        {tracker.items.length === 0 ? (
                            <Empty className="gap-4 p-6">
                                <EmptyHeader>
                                    <EmptyMedia
                                        variant="icon"
                                        className="size-8 [&_svg]:size-5"
                                    >
                                        <Inbox />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        Nothing in review right now
                                    </EmptyTitle>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <div className="divide-y">
                                {tracker.items.map((item) => (
                                    <Link
                                        key={item.id}
                                        href={item.href}
                                        className="block py-3 first:pt-0 last:pb-0 focus-visible:focus-ring-edge"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div className="flex items-center gap-2">
                                                <FormTypeBadge
                                                    label={item.formTypeLabel}
                                                />
                                                <StatusBadge
                                                    status={item.status}
                                                />
                                            </div>
                                            {item.waitingSince && (
                                                <span className="text-xs text-muted-foreground">
                                                    Waiting since{' '}
                                                    <RelativeTime
                                                        dateString={
                                                            item.waitingSince
                                                        }
                                                    />
                                                </span>
                                            )}
                                        </div>
                                        <p className="mt-1.5 truncate text-sm font-semibold">
                                            {item.title}
                                        </p>
                                        <div className="mt-2.5">
                                            <DocumentStepTracker
                                                steps={item.steps}
                                            />
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card className="lg:col-span-4">
                    <CardHeader>
                        <CardTitle className="text-base">Checklist</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <RequirementsChecklist data={requirements} />
                    </CardContent>
                </Card>
            </div>

            {/* Five KPI cards. */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <StatTile
                    label="Total submitted this academic year"
                    count={kpis.totalSubmitted.count}
                    href={kpis.totalSubmitted.href}
                    icon={FileText}
                    tone="primary"
                />
                <StatTile
                    label="In review"
                    count={kpis.inReview.count}
                    href={kpis.inReview.href}
                    icon={Inbox}
                    tone="neutral"
                />
                <StatTile
                    label="Needs revision"
                    count={kpis.needsRevision.count}
                    href={kpis.needsRevision.href}
                    icon={AlarmClock}
                    tone="warning"
                />
                <StatTile
                    label="Approved this academic year"
                    count={kpis.approved.count}
                    href={kpis.approved.href}
                    icon={CircleCheck}
                    tone="success"
                />
                <StatTile
                    label="Avg. time to decision"
                    count={kpis.averageTimeToDecision.sampleSize}
                    displayValue={formatDuration(
                        kpis.averageTimeToDecision.hours,
                    )}
                    href={kpis.averageTimeToDecision.href}
                    icon={Timer}
                    tone="neutral"
                    hint={
                        kpis.averageTimeToDecision.sampleSize > 0
                            ? `Across ${kpis.averageTimeToDecision.sampleSize} decision${kpis.averageTimeToDecision.sampleSize === 1 ? '' : 's'} this academic year`
                            : 'No decisions yet this academic year'
                    }
                />
            </div>

            {/* Quick Submit (6 cols) + Upcoming Activities (6 cols). */}
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                <Card className="lg:col-span-6">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Quick Submit
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="divide-y">
                        {quickSubmit.map((tile) => {
                            const Icon =
                                QUICK_SUBMIT_ICON[tile.formType] ?? FileText;

                            return (
                                <div
                                    key={tile.formType}
                                    className="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                                >
                                    <div className="flex items-center gap-2.5">
                                        <span
                                            className={
                                                tile.enabled
                                                    ? 'flex size-8 shrink-0 items-center justify-center rounded-full bg-primary-text/10 text-primary-text'
                                                    : 'flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground'
                                            }
                                        >
                                            <Icon
                                                className="size-4"
                                                aria-hidden
                                            />
                                        </span>
                                        <div>
                                            <p className="text-sm font-medium">
                                                {tile.label}
                                            </p>
                                            {!tile.enabled && tile.reason && (
                                                <p className="text-xs text-muted-foreground">
                                                    {tile.reason}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                    {tile.enabled ? (
                                        <Button asChild size="sm">
                                            <Link href={tile.href}>
                                                Submit
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button size="sm" disabled>
                                            <Ban data-icon="inline-start" />
                                            Unavailable
                                        </Button>
                                    )}
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>

                <Card className="lg:col-span-6">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Upcoming Activities
                        </CardTitle>
                        <CardDescription>
                            Approved activities in the next 30 days
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        {upcoming.upcoming.length === 0 ? (
                            <Empty className="gap-4 p-6">
                                <EmptyHeader>
                                    <EmptyMedia
                                        variant="icon"
                                        className="size-8 [&_svg]:size-5"
                                    >
                                        <CalendarClock />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        No approved activities in the next 30
                                        days
                                    </EmptyTitle>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <div className="divide-y">
                                {upcoming.upcoming.map((activity) => (
                                    <Link
                                        key={activity.id}
                                        href={activity.href}
                                        className="flex items-start justify-between gap-3 py-2.5 first:pt-0 last:pb-0 focus-visible:focus-ring-edge"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-semibold">
                                                {activity.title}
                                            </p>
                                            <p className="mt-1 truncate text-sm text-muted-foreground">
                                                {activity.venue}
                                            </p>
                                        </div>
                                        <div className="shrink-0 text-right text-sm">
                                            <p className="font-medium tabular-nums">
                                                {formatDate(activity.date)}
                                            </p>
                                            <p className="text-xs text-muted-foreground tabular-nums">
                                                {formatTime(
                                                    activity.startTime,
                                                )}
                                            </p>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        )}

                        {upcoming.reportsDue.length > 0 && (
                            <div className="rounded-md border border-warning/30 bg-warning/10 p-3">
                                <p className="text-xs font-semibold text-warning">
                                    Reports to file
                                </p>
                                <div className="mt-1.5 divide-y divide-warning/20">
                                    {upcoming.reportsDue.map((report) => (
                                        <div
                                            key={report.id}
                                            className="flex items-center justify-between gap-3 py-1.5 first:pt-0 last:pb-0"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium">
                                                    {report.title}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {report.daysSince}{' '}
                                                    day
                                                    {report.daysSince === 1
                                                        ? ''
                                                        : 's'}{' '}
                                                    since the activity
                                                </p>
                                            </div>
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link href={report.fileHref}>
                                                    File report
                                                </Link>
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Submissions Over Time (8 cols) + Recent Notifications (4 cols). */}
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                <Card className="lg:col-span-8">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Submissions Over Time
                        </CardTitle>
                        <CardDescription>
                            Documents your organization submitted per month,
                            last 8 months
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <SubmissionsChart months={submissions} />
                    </CardContent>
                </Card>

                <Card className="lg:col-span-4">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Recent Notifications
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        {recentNotifications.length === 0 ? (
                            <Empty className="gap-4 border-none p-6">
                                <EmptyHeader>
                                    <EmptyMedia
                                        variant="icon"
                                        className="size-8 [&_svg]:size-5"
                                    >
                                        <CircleCheck />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        Nothing new right now
                                    </EmptyTitle>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <ul>
                                {recentNotifications.map((item) => (
                                    <NotificationRow
                                        key={item.id}
                                        item={item}
                                        onRowClick={() =>
                                            visitNotification(item)
                                        }
                                        onMarkRead={() => markRowRead(item)}
                                    />
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
