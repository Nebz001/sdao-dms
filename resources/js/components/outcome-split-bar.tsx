import { Bar, BarChart, XAxis, YAxis } from 'recharts';
import ChartEmpty from '@/components/chart-empty';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';

type OutcomeSplit = {
    approved: number;
    returned: number;
    rejected: number;
};

type OutcomeSplitBarProps = {
    data: OutcomeSplit;
};

const chartConfig = {
    approved: { label: 'Approved', color: 'var(--success)' },
    returned: { label: 'Returned', color: 'var(--warning)' },
    rejected: { label: 'Rejected', color: 'var(--destructive)' },
} satisfies ChartConfig;

const ROWS: Array<{
    key: keyof OutcomeSplit;
    label: string;
    colorVar: string;
}> = [
    { key: 'approved', label: 'Approved', colorVar: 'var(--success)' },
    { key: 'returned', label: 'Returned', colorVar: 'var(--warning)' },
    { key: 'rejected', label: 'Rejected', colorVar: 'var(--destructive)' },
];

/**
 * One stacked horizontal bar for the academic year's Approved/Returned/
 * Rejected split, plus a visible legend row (label, count, share of total) beneath
 * it — the legend is this chart's accessible text summary, same convention
 * as status-distribution-pie.tsx's legend, rather than a separate hidden
 * block.
 */
export default function OutcomeSplitBar({ data }: OutcomeSplitBarProps) {
    const total = data.approved + data.returned + data.rejected;

    if (total === 0) {
        return <ChartEmpty />;
    }

    const chartData = [{ name: 'outcomes', ...data }];

    return (
        <div className="space-y-4">
            <ChartContainer config={chartConfig} className="h-[56px] w-full">
                <BarChart
                    accessibilityLayer
                    data={chartData}
                    layout="vertical"
                    margin={{ top: 0, bottom: 0, left: 0, right: 0 }}
                >
                    <XAxis type="number" hide />
                    <YAxis type="category" dataKey="name" hide />
                    <ChartTooltip
                        cursor={false}
                        content={<ChartTooltipContent indicator="dot" />}
                    />
                    <Bar
                        dataKey="approved"
                        stackId="outcomes"
                        fill="var(--color-approved)"
                        radius={[4, 0, 0, 4]}
                        barSize={32}
                    />
                    <Bar
                        dataKey="returned"
                        stackId="outcomes"
                        fill="var(--color-returned)"
                        barSize={32}
                    />
                    <Bar
                        dataKey="rejected"
                        stackId="outcomes"
                        fill="var(--color-rejected)"
                        radius={[0, 4, 4, 0]}
                        barSize={32}
                    />
                </BarChart>
            </ChartContainer>

            <div className="flex flex-col gap-1.5">
                {ROWS.map((row) => {
                    const count = data[row.key];
                    const share =
                        total > 0 ? Math.round((count / total) * 100) : 0;

                    return (
                        <div
                            key={row.key}
                            className="flex items-center gap-1.5 text-sm"
                        >
                            <span
                                className="size-2.5 shrink-0 rounded-[2px]"
                                style={{ backgroundColor: row.colorVar }}
                                aria-hidden
                            />
                            <span className="text-muted-foreground">
                                {row.label}
                            </span>
                            <span className="ml-auto font-medium tabular-nums">
                                {count}
                                <span className="ml-1 text-xs text-muted-foreground">
                                    ({share}%)
                                </span>
                            </span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
