import { Pie, PieChart } from 'recharts';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { Separator } from '@/components/ui/separator';
import { statusLabel } from '@/lib/utils';

type StatusCount = {
    status: string;
    count: number;
};

type StatusDistributionPieProps = {
    data: StatusCount[];
};

/**
 * Fixed status → color mapping, reusing the exact same semantic tokens
 * StatusBadge/ActionBadge/the old StatusDistributionBar all use (never a
 * second palette for the same five statuses). Referenced directly as raw
 * CSS custom properties — not routed through ChartContainer's own
 * `--color-KEY` indirection — so the legend swatches below (rendered
 * outside the chart's scoped `[data-chart=id]` style block) resolve to the
 * same color as the wedges without duplicating the mapping.
 */
const STATUS_COLOR_VAR: Record<string, string> = {
    draft: 'var(--muted-foreground)',
    in_review: 'var(--info)',
    returned: 'var(--warning)',
    approved: 'var(--success)',
    rejected: 'var(--destructive)',
};

const STATUS_ORDER = ['draft', 'in_review', 'returned', 'approved', 'rejected'];

const chartConfig = {
    count: { label: 'Documents' },
    draft: { label: 'Draft', color: STATUS_COLOR_VAR.draft },
    in_review: { label: 'In Review', color: STATUS_COLOR_VAR.in_review },
    returned: { label: 'Returned', color: STATUS_COLOR_VAR.returned },
    approved: { label: 'Approved', color: STATUS_COLOR_VAR.approved },
    rejected: { label: 'Rejected', color: STATUS_COLOR_VAR.rejected },
} satisfies ChartConfig;

/**
 * Replaces the old single stacked progress bar with a real pie chart, built
 * on shadcn's ChartContainer/ChartTooltip so it inherits the same font,
 * tooltip chrome, and theming conventions as every other chart on this
 * dashboard. The legend stays alongside it (never color-only — a status is
 * always paired with its label and count) instead of leaving colored wedges
 * to speak for themselves.
 */
export default function StatusDistributionPie({
    data,
}: StatusDistributionPieProps) {
    const byStatus = new Map(data.map((d) => [d.status, d.count]));
    const total = data.reduce((sum, d) => sum + d.count, 0);
    const segments = STATUS_ORDER.map((status) => ({
        status,
        count: byStatus.get(status) ?? 0,
        fill: STATUS_COLOR_VAR[status],
    })).filter((s) => s.count > 0);

    // The empty-state copy lives in the card's CardDescription (see
    // admin/dashboard.tsx) so it isn't duplicated here.
    if (total === 0) {
        return null;
    }

    return (
        // Grid, not flex, for the row layout: a flex item's `h-full`
        // can't resolve (collapses to 0) when the flex container's own
        // height is itself content-driven rather than definite. A grid
        // track's height is resolved before its items stretch, so the
        // divider's height actually fills the row. `w-full`: the parent
        // CardContent is now a flex container (see admin/dashboard.tsx),
        // and this grid's trailing `1fr` legend column needs a definite
        // container width to resolve against rather than shrinking to the
        // flex item's own content width. gap-8, not gap-4: the previous
        // value read as cramped — pie, divider, and legend need real
        // breathing room between them, not just enough to avoid touching.
        <div className="grid w-full grid-cols-1 items-stretch gap-8 sm:grid-cols-[auto_auto_1fr]">
            {/* Fixed pixel dimensions, not aspect-square + w-full: inside
                this grid row, ResponsiveContainer's 100% width resolves
                against the grid cell's own auto content width once the
                row breakpoint drops the cell to `auto`, which collapses to
                0 and renders nothing. Sized to roughly match the five-item
                legend's own natural height (~115px), not the other way
                around — the legend no longer stretches to fill a taller
                chart than it needs. */}
            <ChartContainer
                config={chartConfig}
                className="mx-auto h-[112px] w-[112px]"
            >
                <PieChart>
                    <ChartTooltip
                        cursor={false}
                        content={<ChartTooltipContent nameKey="status" hideLabel />}
                    />
                    <Pie data={segments} dataKey="count" nameKey="status" />
                </PieChart>
            </ChartContainer>

            <Separator orientation="vertical" className="hidden sm:block" />

            <div className="flex flex-col justify-center gap-1.5">
                {STATUS_ORDER.map((status) => {
                    const count = byStatus.get(status) ?? 0;

                    return (
                        <div
                            key={status}
                            className="flex items-center gap-1.5 text-sm"
                        >
                            <span
                                className="size-2.5 shrink-0 rounded-[2px]"
                                style={{ backgroundColor: STATUS_COLOR_VAR[status] }}
                                aria-hidden
                            />
                            <span className="text-muted-foreground">
                                {statusLabel(status)}
                            </span>
                            <span className="font-medium tabular-nums">
                                {count}
                            </span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
