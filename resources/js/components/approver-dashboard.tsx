import { Link } from '@inertiajs/react';
import {
    AlarmClock,
    CalendarClock,
    ChevronRight,
    CircleCheck,
    History,
    Inbox,
    Timer,
    Undo2,
} from 'lucide-react';
import FormTypeBadge from '@/components/form-type-badge';
import OutcomeSplitBar from '@/components/outcome-split-bar';
import { RelativeTime } from '@/components/relative-time';
import ReviewActivityChart from '@/components/review-activity-chart';
import StatTile from '@/components/stat-tile';
import { ActionBadge, FlagBadge } from '@/components/status-badge';
import TagBadge from '@/components/tag-badge';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import WaitBadge from '@/components/wait-badge';
import WaitingTimeChart from '@/components/waiting-time-chart';

type Href = { count: number; href: string };

export type ApproverKpis = {
    waitingOnYou: Href;
    overdue: Href;
    approved: Href;
    returned: Href;
    averageReviewTime: {
        hours: number | null;
        sampleSize: number;
        href: string;
    };
};

export type PriorityQueueRow = {
    id: number;
    title: string;
    formType: string;
    formTypeLabel: string;
    organizationName: string;
    submittedAt: string | null;
    waitingSince: string;
    daysWaiting: number;
    waitTier: 'normal' | 'warning' | 'overdue';
    /** This document came back to THIS approver after being returned for revision — it may have changed since they last saw it. */
    wasResubmitted: boolean;
    eventDate: string | null;
    eventSoon: boolean;
    href: string;
};

export type WaitingTimeBucket = { tier: string; label: string; count: number };

export type ReviewWeek = {
    weekStart: string;
    label: string;
    approved: number;
    returned: number;
    rejected: number;
    total: number;
};

export type OutcomeSplit = {
    approved: number;
    returned: number;
    rejected: number;
};

export type UpcomingEvent = {
    id: number;
    title: string;
    organizationName: string;
    venue: string;
    date: string;
    startTime: string;
    endTime: string;
    href: string;
};

export type RecentDecision = {
    id: number;
    action: string;
    formType: string;
    formTypeLabel: string;
    documentTitle: string;
    organizationName: string;
    createdAt: string;
    href: string;
};

type ApproverDashboardProps = {
    meta: {
        overdueAfterDays: number;
        reviewHref: string;
        academicYear: string;
    };
    kpis: ApproverKpis;
    queue: PriorityQueueRow[];
    waitingTime: WaitingTimeBucket[];
    reviewActivity: ReviewWeek[];
    outcomeSplit: OutcomeSplit;
    upcomingEvents: UpcomingEvent[];
    recentDecisions: RecentDecision[];
};

/** "18 min" under an hour, "6 hrs" under 2 days, otherwise "2.4 days" — whichever reads better at that scale. */
function formatReviewDuration(hours: number | null): string {
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

export default function ApproverDashboard({
    meta,
    kpis,
    queue,
    waitingTime,
    reviewActivity,
    outcomeSplit,
    upcomingEvents,
    recentDecisions,
}: ApproverDashboardProps) {
    const decisionsTotal =
        outcomeSplit.approved + outcomeSplit.returned + outcomeSplit.rejected;

    return (
        <div className="flex flex-col gap-4">
            {/* Row 1 — five KPI cards. */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <StatTile
                    label="Waiting on you"
                    count={kpis.waitingOnYou.count}
                    href={kpis.waitingOnYou.href}
                    icon={Inbox}
                    tone="primary"
                />
                <StatTile
                    label="Overdue"
                    count={kpis.overdue.count}
                    href={kpis.overdue.href}
                    icon={AlarmClock}
                    tone="destructive"
                    hint={`Waiting more than ${meta.overdueAfterDays} days`}
                />
                <StatTile
                    label="Approved this academic year"
                    count={kpis.approved.count}
                    href={kpis.approved.href}
                    icon={CircleCheck}
                    tone="success"
                />
                <StatTile
                    label="Returned for revision"
                    count={kpis.returned.count}
                    href={kpis.returned.href}
                    icon={Undo2}
                    tone="warning"
                />
                <StatTile
                    label="Avg. review time"
                    count={kpis.averageReviewTime.sampleSize}
                    displayValue={formatReviewDuration(
                        kpis.averageReviewTime.hours,
                    )}
                    href={kpis.averageReviewTime.href}
                    icon={Timer}
                    tone="neutral"
                    hint={
                        kpis.averageReviewTime.sampleSize > 0
                            ? `Across ${kpis.averageReviewTime.sampleSize} decision${kpis.averageReviewTime.sampleSize === 1 ? '' : 's'}`
                            : 'No decisions yet this academic year'
                    }
                />
            </div>

            {/* Row 2 — Priority Queue (8 cols) + Pending by Document Type (4 cols). */}
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                <Card className="lg:col-span-8">
                    <CardHeader className="flex flex-row items-start justify-between gap-2">
                        <div>
                            <CardTitle className="text-base">
                                Priority Queue
                            </CardTitle>
                            <CardDescription>
                                {queue.length === 0
                                    ? 'Nothing is waiting at your step right now.'
                                    : `Oldest and most urgent first, top ${queue.length} of your queue.`}
                            </CardDescription>
                        </div>
                        <Link
                            href={meta.reviewHref}
                            className="inline-flex shrink-0 items-center gap-1 text-sm text-primary-text hover:underline"
                        >
                            View all
                            <ChevronRight className="size-3.5" />
                        </Link>
                    </CardHeader>
                    <CardContent>
                        {queue.length === 0 ? (
                            <Empty className="gap-4 p-6">
                                <EmptyHeader>
                                    <EmptyMedia
                                        variant="icon"
                                        className="size-8 [&_svg]:size-5"
                                    >
                                        <CircleCheck />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        You&apos;re all caught up
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        Documents will show up here once they
                                        reach a step routed to your role.
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Organization</TableHead>
                                        <TableHead>Submitted</TableHead>
                                        <TableHead>Waiting since</TableHead>
                                        <TableHead>Event date</TableHead>
                                        <TableHead className="text-right">
                                            <span className="sr-only">
                                                Action
                                            </span>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {queue.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell>
                                                <FormTypeBadge
                                                    label={row.formTypeLabel}
                                                />
                                            </TableCell>
                                            <TableCell className="max-w-40 truncate">
                                                {row.organizationName}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {row.submittedAt
                                                    ? formatDate(
                                                          row.submittedAt,
                                                      )
                                                    : '—'}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-col gap-1">
                                                    <div className="flex items-center gap-1.5">
                                                        <WaitBadge
                                                            days={
                                                                row.daysWaiting
                                                            }
                                                            tier={row.waitTier}
                                                        />
                                                        <span className="text-xs text-muted-foreground">
                                                            since{' '}
                                                            {formatDate(
                                                                row.waitingSince,
                                                            )}
                                                        </span>
                                                    </div>
                                                    {row.wasResubmitted && (
                                                        <FlagBadge flag="resubmitted" className="w-fit" />
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-1.5">
                                                    <span className="text-muted-foreground">
                                                        {row.eventDate
                                                            ? formatDate(
                                                                  row.eventDate,
                                                              )
                                                            : '—'}
                                                    </span>
                                                    {row.eventSoon && (
                                                        <FlagBadge flag="urgent" />
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button asChild size="sm">
                                                    <Link href={row.href}>
                                                        Review
                                                    </Link>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                <Card className="lg:col-span-4">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Waiting Time
                        </CardTitle>
                        <CardDescription>
                            {waitingTime.reduce((sum, b) => sum + b.count, 0) >
                            0
                                ? `${waitingTime.reduce((sum, b) => sum + b.count, 0)} documents currently waiting on you`
                                : 'Nothing waiting on you right now'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <WaitingTimeChart buckets={waitingTime} />
                    </CardContent>
                </Card>
            </div>

            {/* Row 3 — Review Activity (8 cols) + Outcome Split (4 cols). */}
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                <Card className="lg:col-span-8">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Review Activity
                        </CardTitle>
                        <CardDescription>
                            Documents you reviewed per week, last 8 weeks
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ReviewActivityChart weeks={reviewActivity} />
                    </CardContent>
                </Card>

                <Card className="lg:col-span-4">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Outcome Split
                        </CardTitle>
                        <CardDescription>
                            {decisionsTotal > 0
                                ? `${decisionsTotal} decisions this academic year`
                                : 'No decisions yet this academic year'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <OutcomeSplitBar data={outcomeSplit} />
                    </CardContent>
                </Card>
            </div>

            {/* Row 4 — Upcoming Events (6 cols) + Recent Decisions (6 cols). */}
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                <Card className="lg:col-span-6">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Upcoming Events This Week
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {upcomingEvents.length === 0 ? (
                            <Empty className="gap-4 p-6">
                                <EmptyHeader>
                                    <EmptyMedia
                                        variant="icon"
                                        className="size-8 [&_svg]:size-5"
                                    >
                                        <CalendarClock />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        No approved activities in the next 7
                                        days
                                    </EmptyTitle>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <div className="divide-y">
                                {upcomingEvents.map((event) => (
                                    <div
                                        key={event.id}
                                        className="flex items-start justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="sm:truncate max-sm:break-words text-sm font-semibold">
                                                {event.title}
                                            </p>
                                            <p className="mt-1 sm:truncate max-sm:break-words text-sm text-muted-foreground">
                                                {event.organizationName} ·{' '}
                                                {event.venue}
                                            </p>
                                        </div>
                                        <div className="shrink-0 text-right text-sm">
                                            <p className="font-medium tabular-nums">
                                                {formatDate(event.date)}
                                            </p>
                                            <p className="text-xs text-muted-foreground tabular-nums">
                                                {formatTime(event.startTime)}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card className="lg:col-span-6">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Recent Decisions
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recentDecisions.length === 0 ? (
                            <Empty className="gap-4 p-6">
                                <EmptyHeader>
                                    <EmptyMedia
                                        variant="icon"
                                        className="size-8 [&_svg]:size-5"
                                    >
                                        <History />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        You haven&apos;t made any decisions yet
                                    </EmptyTitle>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <div className="divide-y">
                                {recentDecisions.map((entry) => (
                                    <div
                                        key={entry.id}
                                        className="flex items-start justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <Link
                                                href={entry.href}
                                                className="block sm:truncate max-sm:break-words text-sm font-semibold hover:underline"
                                            >
                                                {entry.documentTitle}
                                            </Link>
                                            <div className="mt-1.5 flex flex-wrap items-center gap-2">
                                                <ActionBadge
                                                    action={entry.action}
                                                />
                                                <FormTypeBadge
                                                    label={entry.formTypeLabel}
                                                />
                                                <TagBadge>{entry.organizationName}</TagBadge>
                                            </div>
                                        </div>
                                        <span className="w-20 shrink-0 text-right text-sm text-muted-foreground tabular-nums">
                                            <RelativeTime
                                                dateString={entry.createdAt}
                                            />
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
