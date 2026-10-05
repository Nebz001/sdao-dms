import { Link } from '@inertiajs/react';
import { Inbox } from 'lucide-react';
import AccountName from '@/components/account-name';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Skeleton } from '@/components/ui/skeleton';
import { NO_SCHOOL_LABEL } from '@/lib/school';
import DataTable, { RowViewButton } from './data-table';
import type { DataColumn } from './data-table';
import ResultPill from './result-pill';
import { formatDate } from './types';
import type { QueueRow, RecentDecision, ReviewQueueConfig } from './types';
import WaitPill from './wait-pill';

/** Card with a title (and optional count badge) on the left and a quiet label on the right. */
export function SectionCard({
    title,
    count,
    aside,
    children,
}: {
    title: string;
    count?: number;
    aside?: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <Card className="gap-4 shadow-none">
            <CardHeader className="flex flex-row items-center justify-between gap-3">
                <CardTitle className="flex items-center gap-2 text-base">
                    {title}
                    {count !== undefined && (
                        <Badge variant="secondary">{count}</Badge>
                    )}
                </CardTitle>
                {aside && (
                    <div className="text-sm text-muted-foreground">{aside}</div>
                )}
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

/** An organization name, optionally with its college in muted text underneath. */
function OrganizationCell({
    name,
    college,
    showCollege,
    strong,
}: {
    name: string;
    college?: string | null;
    showCollege: boolean;
    strong: boolean;
}) {
    return (
        <div className="flex flex-col">
            <span className={strong ? 'font-medium' : undefined}>{name}</span>
            {showCollege && (
                <span className="text-sm text-muted-foreground">
                    {college ?? NO_SCHOOL_LABEL}
                </span>
            )}
        </div>
    );
}

export function PendingTable({
    rows,
    extraColumnLabel,
    config,
    title = 'Pending',
    emptyTitle = 'Nothing to review',
    showActivity = false,
    showStep = false,
    flagOverdue = false,
    showCollege = false,
}: {
    rows: QueueRow[];
    /** Omit when the queue has no page-specific column (activity proposals show the step instead). */
    extraColumnLabel?: string;
    config: ReviewQueueConfig;
    title?: string;
    emptyTitle?: string;
    /** Leads with the document title, in medium weight, with the organization as its own column. */
    showActivity?: boolean;
    /** Adds the Current step column: "Step 2 of 4" over the step name. */
    showStep?: boolean;
    /** Waiting pill reads "9 days, overdue" for overdue rows. */
    flagOverdue?: boolean;
    /** Shows each row's college (the row's `extra`) under the organization name. */
    showCollege?: boolean;
}) {
    const columns: DataColumn<QueueRow>[] = [
        ...(showActivity
            ? [
                  {
                      key: 'title',
                      header: 'Activity',
                      slot: 'title' as const,
                      cell: (r: QueueRow) => (
                          <span className="font-medium">{r.title}</span>
                      ),
                  },
              ]
            : []),
        {
            key: 'organization',
            header: 'Organization',
            slot: showActivity ? undefined : 'title',
            cell: (r) => (
                <OrganizationCell
                    name={r.organization.name}
                    college={r.college}
                    showCollege={showCollege}
                    strong={!showActivity}
                />
            ),
        },
        ...(extraColumnLabel
            ? [
                  {
                      key: 'extra',
                      header: extraColumnLabel,
                      cell: (r: QueueRow) => r.extra ?? 'None',
                  },
              ]
            : []),
        {
            key: 'submitted',
            header: 'Submitted',
            className: 'tabular-nums',
            cell: (r) => formatDate(r.submitted_at),
        },
        ...(showStep
            ? [
                  {
                      key: 'step',
                      header: 'Current step',
                      cell: (r: QueueRow) =>
                          r.step ? (
                              <div className="flex flex-col">
                                  <span>
                                      Step {r.step.position} of {r.step.total}
                                  </span>
                                  <span className="text-sm text-muted-foreground">
                                      {r.step.name}
                                  </span>
                              </div>
                          ) : (
                              <span className="text-muted-foreground">—</span>
                          ),
                  },
              ]
            : []),
        {
            key: 'waiting',
            header: 'Waiting',
            slot: 'badge',
            cell: (r) => (
                <WaitPill
                    days={r.waiting_days}
                    tier={r.tier}
                    flagOverdue={flagOverdue}
                />
            ),
        },
        {
            key: 'actions',
            header: 'Action',
            slot: 'action',
            align: 'right',
            cell: (r) => (
                <Button asChild size="sm">
                    <Link href={r.href ?? config.showRoute(r.id)}>
                        Review
                        <span className="sr-only">
                            {' '}
                            {showActivity ? r.title : r.organization.name}
                        </span>
                    </Link>
                </Button>
            ),
        },
    ];

    return (
        <SectionCard title={title} count={rows.length} aside="Oldest first">
            {rows.length === 0 ? (
                <Empty>
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <Inbox />
                        </EmptyMedia>
                        <EmptyTitle>{emptyTitle}</EmptyTitle>
                        <EmptyDescription>
                            {config.emptyDescription}
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <DataTable
                    rows={rows}
                    columns={columns}
                    rowKey={(r) => r.id}
                    roomy={showActivity}
                />
            )}
        </SectionCard>
    );
}

export function RecentDecisionsTable({
    rows,
    title = 'Recently decided',
    aside = 'Last 30 days',
    emptyText = 'No decisions in the last 30 days.',
    showActivity = false,
    showDecidedBy = true,
    showCollege = false,
}: {
    rows: RecentDecision[];
    title?: string;
    aside?: string;
    emptyText?: string;
    /** Leads with the document title; rows must carry `title`. */
    showActivity?: boolean;
    showDecidedBy?: boolean;
    /** Shows each row's college under the organization name. */
    showCollege?: boolean;
}) {
    const columns: DataColumn<RecentDecision>[] = [
        ...(showActivity
            ? [
                  {
                      key: 'title',
                      header: 'Activity',
                      slot: 'title' as const,
                      cell: (r: RecentDecision) => (
                          <span className="font-medium">{r.title}</span>
                      ),
                  },
              ]
            : []),
        {
            key: 'organization',
            header: 'Organization',
            slot: showActivity ? undefined : 'title',
            cell: (r) => (
                <OrganizationCell
                    name={r.organization}
                    college={r.college}
                    showCollege={showCollege}
                    strong={!showActivity}
                />
            ),
        },
        {
            key: 'result',
            header: 'Result',
            slot: 'badge',
            cell: (r) => <ResultPill result={r.result} />,
        },
        {
            key: 'decided',
            header: 'Decided on',
            className: 'tabular-nums',
            cell: (r) => formatDate(r.decided_at),
        },
        ...(showDecidedBy
            ? [
                  {
                      key: 'by',
                      header: 'Decided by',
                      cell: (r: RecentDecision) =>
                          r.decided_by ? (
                              <AccountName
                                  name={r.decided_by}
                                  nameClassName="font-normal"
                              />
                          ) : (
                              '—'
                          ),
                  },
              ]
            : []),
        {
            key: 'actions',
            header: 'Action',
            slot: 'action',
            align: 'right',
            cell: (r) => (
                <RowViewButton
                    href={r.href}
                    label={
                        showActivity
                            ? (r.title ?? r.organization)
                            : r.organization
                    }
                />
            ),
        },
    ];

    return (
        <SectionCard title={title} aside={aside}>
            {rows.length === 0 ? (
                showActivity ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Inbox />
                            </EmptyMedia>
                            <EmptyTitle>No decisions to show</EmptyTitle>
                            <EmptyDescription>{emptyText}</EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <p className="py-6 text-center text-sm text-muted-foreground">
                        {emptyText}
                    </p>
                )
            ) : (
                <DataTable
                    rows={rows}
                    columns={columns}
                    rowKey={(r) => r.id}
                    roomy={showActivity}
                />
            )}
        </SectionCard>
    );
}

/** Same card and row rhythm as the real table while the deferred rows load. */
export function RecentDecisionsSkeleton({
    title = 'Recently decided',
    aside = 'Last 30 days',
}: {
    title?: string;
    aside?: string;
}) {
    return (
        <SectionCard title={title} aside={aside}>
            <div aria-busy="true" className="flex flex-col gap-3">
                {[0, 1, 2].map((i) => (
                    <Skeleton key={i} className="h-10 w-full" />
                ))}
            </div>
        </SectionCard>
    );
}
