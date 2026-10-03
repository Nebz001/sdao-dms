import { Link } from '@inertiajs/react';
import { Inbox } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';
import ResultPill from './result-pill';
import { formatDate } from './types';
import type { QueueRow, RecentDecision, ReviewQueueConfig } from './types';
import WaitPill from './wait-pill';

const HEAD = 'text-xs font-medium tracking-wide text-muted-foreground uppercase';

/** Card with a title (and optional count badge) on the left and a quiet label on the right. */
export function SectionCard({
    title,
    count,
    aside,
    children,
}: {
    title: string;
    count?: number;
    aside: string;
    children: React.ReactNode;
}) {
    return (
        <Card className="gap-4 shadow-none">
            <CardHeader className="flex flex-row items-center justify-between gap-3">
                <CardTitle className="flex items-center gap-2 text-base">
                    {title}
                    {count !== undefined && <Badge variant="secondary">{count}</Badge>}
                </CardTitle>
                <span className="text-sm text-muted-foreground">{aside}</span>
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
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
}: {
    rows: QueueRow[];
    /** Omit when the queue has no page-specific column (activity proposals show the step instead). */
    extraColumnLabel?: string;
    config: ReviewQueueConfig;
    title?: string;
    emptyTitle?: string;
    /** Leads with the document title, bold, with the organization as its own column. */
    showActivity?: boolean;
    /** Adds the Current step column: "Step 2 of 4" over the step name. */
    showStep?: boolean;
    /** Waiting pill reads "9 days, overdue" for overdue rows. */
    flagOverdue?: boolean;
}) {
    return (
        <SectionCard title={title} count={rows.length} aside="Oldest first">
            {rows.length === 0 ? (
                <Empty>
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <Inbox />
                        </EmptyMedia>
                        <EmptyTitle>{emptyTitle}</EmptyTitle>
                        <EmptyDescription>{config.emptyDescription}</EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <Table>
                    <TableHeader>
                        <TableRow>
                            {showActivity && <TableHead className={HEAD}>Activity</TableHead>}
                            <TableHead className={HEAD}>Organization</TableHead>
                            {extraColumnLabel && <TableHead className={HEAD}>{extraColumnLabel}</TableHead>}
                            <TableHead className={HEAD}>Submitted</TableHead>
                            {showStep && <TableHead className={HEAD}>Current step</TableHead>}
                            <TableHead className={HEAD}>Waiting</TableHead>
                            <TableHead>
                                <span className="sr-only">Actions</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.map((row) => (
                            <TableRow key={row.id}>
                                {showActivity && <TableCell className="font-semibold whitespace-normal">{row.title}</TableCell>}
                                <TableCell className={cn('whitespace-normal', !showActivity && 'font-medium')}>
                                    {row.organization.name}
                                </TableCell>
                                {extraColumnLabel && (
                                    <TableCell className="whitespace-normal">{row.extra ?? 'None'}</TableCell>
                                )}
                                <TableCell className="tabular-nums">{formatDate(row.submitted_at)}</TableCell>
                                {showStep && (
                                    <TableCell>
                                        {row.step ? (
                                            <div className="flex flex-col">
                                                <span>
                                                    Step {row.step.position} of {row.step.total}
                                                </span>
                                                <span className="text-sm text-muted-foreground">{row.step.name}</span>
                                            </div>
                                        ) : (
                                            <span className="text-muted-foreground">—</span>
                                        )}
                                    </TableCell>
                                )}
                                <TableCell>
                                    <WaitPill days={row.waiting_days} tier={row.tier} flagOverdue={flagOverdue} />
                                </TableCell>
                                <TableCell className="text-right">
                                    <Button asChild size="sm">
                                        <Link href={config.showRoute(row.id)}>
                                            Review<span className="sr-only"> {showActivity ? row.title : row.organization.name}</span>
                                        </Link>
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
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
}: {
    rows: RecentDecision[];
    title?: string;
    aside?: string;
    emptyText?: string;
    /** Leads with the document title; rows must carry `title`. */
    showActivity?: boolean;
    showDecidedBy?: boolean;
}) {
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
                    <p className="py-6 text-center text-sm text-muted-foreground">{emptyText}</p>
                )
            ) : (
                <Table>
                    <TableHeader>
                        <TableRow>
                            {showActivity && <TableHead className={HEAD}>Activity</TableHead>}
                            <TableHead className={HEAD}>Organization</TableHead>
                            <TableHead className={HEAD}>Result</TableHead>
                            <TableHead className={HEAD}>Decided on</TableHead>
                            {showDecidedBy && <TableHead className={HEAD}>Decided by</TableHead>}
                            <TableHead>
                                <span className="sr-only">Actions</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.map((row) => (
                            <TableRow key={row.id}>
                                {showActivity && <TableCell className="font-semibold whitespace-normal">{row.title}</TableCell>}
                                <TableCell className={cn('whitespace-normal', !showActivity && 'font-medium')}>
                                    {row.organization}
                                </TableCell>
                                <TableCell>
                                    <ResultPill result={row.result} />
                                </TableCell>
                                <TableCell className="tabular-nums">{formatDate(row.decided_at)}</TableCell>
                                {showDecidedBy && (
                                    <TableCell className="whitespace-normal">{row.decided_by ?? '—'}</TableCell>
                                )}
                                <TableCell className="text-right">
                                    <Button asChild size="sm" variant="secondary">
                                        <Link href={row.href}>
                                            View<span className="sr-only"> {showActivity ? row.title : row.organization}</span>
                                        </Link>
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
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
