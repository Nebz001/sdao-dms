import { Link } from '@inertiajs/react';
import { Inbox } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import ResultPill from './result-pill';
import { formatDate } from './types';
import type { QueueRow, RecentDecision, ReviewQueueConfig } from './types';
import WaitPill from './wait-pill';

const HEAD = 'text-xs font-medium tracking-wide text-muted-foreground uppercase';

/** Card with a title (and optional count badge) on the left and a quiet label on the right. */
function SectionCard({
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
}: {
    rows: QueueRow[];
    extraColumnLabel: string;
    config: ReviewQueueConfig;
}) {
    return (
        <SectionCard title="Pending" count={rows.length} aside="Oldest first">
            {rows.length === 0 ? (
                <Empty>
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <Inbox />
                        </EmptyMedia>
                        <EmptyTitle>Nothing to review</EmptyTitle>
                        <EmptyDescription>{config.emptyDescription}</EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className={HEAD}>Organization</TableHead>
                            <TableHead className={HEAD}>{extraColumnLabel}</TableHead>
                            <TableHead className={HEAD}>Submitted</TableHead>
                            <TableHead className={HEAD}>Waiting</TableHead>
                            <TableHead>
                                <span className="sr-only">Actions</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.map((row) => (
                            <TableRow key={row.id}>
                                <TableCell className="font-medium whitespace-normal">{row.organization.name}</TableCell>
                                <TableCell className="whitespace-normal">{row.extra ?? 'None'}</TableCell>
                                <TableCell className="tabular-nums">{formatDate(row.submitted_at)}</TableCell>
                                <TableCell>
                                    <WaitPill days={row.waiting_days} tier={row.tier} />
                                </TableCell>
                                <TableCell className="text-right">
                                    <Button asChild size="sm">
                                        <Link href={config.showRoute(row.id)}>
                                            Review<span className="sr-only"> {row.organization.name}</span>
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

export function RecentDecisionsTable({ rows }: { rows: RecentDecision[] }) {
    return (
        <SectionCard title="Recently decided" aside="Last 30 days">
            {rows.length === 0 ? (
                <p className="py-6 text-center text-sm text-muted-foreground">
                    No decisions in the last 30 days.
                </p>
            ) : (
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className={HEAD}>Organization</TableHead>
                            <TableHead className={HEAD}>Result</TableHead>
                            <TableHead className={HEAD}>Decided on</TableHead>
                            <TableHead className={HEAD}>Decided by</TableHead>
                            <TableHead>
                                <span className="sr-only">Actions</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.map((row) => (
                            <TableRow key={row.id}>
                                <TableCell className="font-medium whitespace-normal">{row.organization}</TableCell>
                                <TableCell>
                                    <ResultPill result={row.result} />
                                </TableCell>
                                <TableCell className="tabular-nums">{formatDate(row.decided_at)}</TableCell>
                                <TableCell className="whitespace-normal">{row.decided_by ?? '—'}</TableCell>
                                <TableCell className="text-right">
                                    <Button asChild size="sm" variant="secondary">
                                        <Link href={row.href}>
                                            View<span className="sr-only"> {row.organization}</span>
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
export function RecentDecisionsSkeleton() {
    return (
        <SectionCard title="Recently decided" aside="Last 30 days">
            <div aria-busy="true" className="flex flex-col gap-3">
                {[0, 1, 2].map((i) => (
                    <Skeleton key={i} className="h-10 w-full" />
                ))}
            </div>
        </SectionCard>
    );
}
