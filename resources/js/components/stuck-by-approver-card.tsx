import { Link } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import IdleBadge from '@/components/idle-badge';
import type { IdleTier } from '@/components/idle-badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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

export type StuckApprover = {
    key: string;
    name: string;
    line: string;
    waiting: number;
    oldest: number;
    median: number;
    tier: IdleTier;
    href: string;
};

export type StuckByApprover = {
    rows: StuckApprover[];
    total: number;
    viewAllHref: string;
};

const COLUMN_HEAD =
    'h-8 text-xs font-medium tracking-wide text-muted-foreground uppercase';

/**
 * Where in-review documents are sitting, one row per approver, oldest wait
 * first. The OLDEST pill always prints the number, so the tier color is never
 * the only signal. Rows are the top few only; "View all" opens the full
 * Stuck Documents page.
 */
export default function StuckByApproverCard({ data }: { data: StuckByApprover }) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-2">
                <div className="grid gap-1.5">
                    <CardTitle className="text-base">Where documents are stuck</CardTitle>
                    <CardDescription>
                        In review documents grouped by the approver whose step they are sitting on
                    </CardDescription>
                </div>
                {data.rows.length > 0 && (
                    <Link
                        href={data.viewAllHref}
                        className="shrink-0 text-sm text-primary-text hover:underline"
                    >
                        View all
                    </Link>
                )}
            </CardHeader>
            <CardContent>
                {data.rows.length === 0 ? (
                    <Empty className="gap-4 p-6">
                        <EmptyHeader>
                            <EmptyMedia variant="icon" className="size-8 [&_svg]:size-5">
                                <CircleCheck />
                            </EmptyMedia>
                            <EmptyTitle>Nothing is stuck</EmptyTitle>
                            <EmptyDescription>
                                No document is waiting on an approver right now.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className={COLUMN_HEAD}>Approver</TableHead>
                                <TableHead className={`${COLUMN_HEAD} text-right`}>Waiting</TableHead>
                                <TableHead className={`${COLUMN_HEAD} text-right`}>Oldest</TableHead>
                                <TableHead className={`${COLUMN_HEAD} text-right`}>Median</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {data.rows.map((row) => (
                                <TableRow key={row.key}>
                                    <TableCell className="max-w-0 min-w-40">
                                        <Link
                                            href={row.href}
                                            className="block truncate text-sm font-semibold hover:underline"
                                            title={row.name}
                                        >
                                            {row.name}
                                        </Link>
                                        <p
                                            className="truncate text-xs text-muted-foreground"
                                            title={row.line}
                                        >
                                            {row.line}
                                        </p>
                                    </TableCell>
                                    <TableCell className="text-right font-semibold tabular-nums">
                                        {row.waiting}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <IdleBadge days={row.oldest} tier={row.tier} />
                                    </TableCell>
                                    <TableCell className="text-right text-sm tabular-nums">
                                        {row.median} d
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </CardContent>
        </Card>
    );
}
