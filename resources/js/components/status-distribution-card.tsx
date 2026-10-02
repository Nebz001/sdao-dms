import { Link, router } from '@inertiajs/react';
import { Pie, PieChart } from 'recharts';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { cn } from '@/lib/utils';

export type StatusCount = {
    status: string;
    count: number;
    /** The list behind this status, or null when none exists (drafts). */
    href: string | null;
};

/**
 * Fixed status to color mapping, the same semantic tokens StatusBadge and
 * ActionBadge use (never a second palette for the same five statuses). The
 * legend swatches sit outside the chart's scoped style block, so they read
 * the raw CSS variables directly.
 */
const STATUS_COLOR_VAR: Record<string, string> = {
    draft: 'var(--muted-foreground)',
    in_review: 'var(--info)',
    returned: 'var(--warning)',
    approved: 'var(--success)',
    rejected: 'var(--destructive)',
};

/** Legend order, and the sentence-case labels the dashboard uses. */
const STATUS_ROWS = [
    { status: 'approved', label: 'Approved' },
    { status: 'in_review', label: 'In review' },
    { status: 'returned', label: 'Returned' },
    { status: 'rejected', label: 'Rejected' },
    { status: 'draft', label: 'Draft' },
];

const chartConfig = {
    count: { label: 'Documents' },
    draft: { label: 'Draft', color: STATUS_COLOR_VAR.draft },
    in_review: { label: 'In review', color: STATUS_COLOR_VAR.in_review },
    returned: { label: 'Returned', color: STATUS_COLOR_VAR.returned },
    approved: { label: 'Approved', color: STATUS_COLOR_VAR.approved },
    rejected: { label: 'Rejected', color: STATUS_COLOR_VAR.rejected },
} satisfies ChartConfig;

type Sector = { payload?: { href?: string | null } };

/**
 * A donut of every document this academic year by status, the total in the
 * center. A segment click and a legend row both open the list behind that
 * status; the legend rows are real links, so the same destinations work from
 * the keyboard. Every status is always named and counted in the legend, so
 * color never carries the meaning alone.
 */
export default function StatusDistributionCard({ data }: { data: StatusCount[] }) {
    const byStatus = new Map(data.map((d) => [d.status, d]));
    const total = data.reduce((sum, d) => sum + d.count, 0);
    const segments = STATUS_ROWS.map(({ status }) => ({
        status,
        count: byStatus.get(status)?.count ?? 0,
        href: byStatus.get(status)?.href ?? null,
        fill: STATUS_COLOR_VAR[status],
    })).filter((s) => s.count > 0);

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Status distribution</CardTitle>
                <CardDescription>
                    {total > 0
                        ? 'All five form types, this academic year'
                        : 'No documents in this academic year yet.'}
                </CardDescription>
            </CardHeader>
            {total > 0 && (
                <CardContent className="flex flex-1 items-center">
                    <div className="grid w-full items-center gap-6 sm:grid-cols-[auto_1fr] sm:gap-8">
                        <div className="relative mx-auto size-44">
                            <ChartContainer config={chartConfig} className="size-44">
                                <PieChart>
                                    <ChartTooltip
                                        cursor={false}
                                        content={<ChartTooltipContent nameKey="status" hideLabel />}
                                    />
                                    <Pie
                                        data={segments}
                                        dataKey="count"
                                        nameKey="status"
                                        innerRadius={58}
                                        outerRadius={84}
                                        strokeWidth={2}
                                        onClick={(sector: Sector) => {
                                            const href = sector.payload?.href;

                                            if (href) {
                                                router.visit(href);
                                            }
                                        }}
                                    />
                                </PieChart>
                            </ChartContainer>
                            <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                                <span className="text-3xl font-semibold tabular-nums">{total}</span>
                                <span className="text-xs text-muted-foreground">documents</span>
                            </div>
                        </div>

                        <ul className="flex flex-col">
                            {STATUS_ROWS.map(({ status, label }) => {
                                const entry = byStatus.get(status);
                                const row = (
                                    <>
                                        <span
                                            className="size-2.5 shrink-0 rounded-full"
                                            style={{ backgroundColor: STATUS_COLOR_VAR[status] }}
                                            aria-hidden
                                        />
                                        <span>{label}</span>
                                        <span className="ml-auto font-semibold tabular-nums">
                                            {entry?.count ?? 0}
                                        </span>
                                    </>
                                );
                                const rowClass = 'flex items-center gap-2.5 rounded-sm px-1 py-1.5 text-sm';

                                return (
                                    <li key={status}>
                                        {entry?.href ? (
                                            <Link
                                                href={entry.href}
                                                className={cn(
                                                    rowClass,
                                                    'hover:bg-muted/60 focus-visible:focus-ring-edge',
                                                )}
                                            >
                                                {row}
                                            </Link>
                                        ) : (
                                            <div className={rowClass}>{row}</div>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                </CardContent>
            )}
        </Card>
    );
}
