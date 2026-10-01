import { Bar, BarChart, CartesianGrid, Cell, XAxis, YAxis } from 'recharts';
import ChartEmpty from '@/components/chart-empty';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';

type WaitingTimeBucket = {
    tier: string;
    label: string;
    count: number;
};

type WaitingTimeChartProps = {
    buckets: WaitingTimeBucket[];
};

/**
 * How long the documents currently in this approver's queue have been
 * waiting, bucketed into the same normal/warning/overdue tiers as
 * WaitBadge/ApproverQueue::waitTier() — same colors too (muted/amber/red),
 * so this chart and the Priority Queue's own badges can never disagree
 * about what "overdue" means.
 */
const TIER_COLOR: Record<string, string> = {
    normal: 'var(--muted-foreground)',
    warning: 'var(--warning)',
    overdue: 'var(--destructive)',
};

const chartConfig = {
    count: { label: 'Documents' },
} satisfies ChartConfig;

export default function WaitingTimeChart({ buckets }: WaitingTimeChartProps) {
    const total = buckets.reduce((sum, b) => sum + b.count, 0);

    if (total === 0) {
        return <ChartEmpty message="Nothing waiting on you right now" />;
    }

    return (
        <ChartContainer config={chartConfig} className="h-[180px] w-full">
            <BarChart accessibilityLayer data={buckets}>
                <CartesianGrid vertical={false} />
                <XAxis
                    dataKey="label"
                    tickLine={false}
                    axisLine={false}
                    tickMargin={8}
                    fontSize={13}
                />
                <YAxis
                    allowDecimals={false}
                    tickLine={false}
                    axisLine={false}
                    width={28}
                    fontSize={13}
                />
                <ChartTooltip
                    cursor={false}
                    content={<ChartTooltipContent hideLabel />}
                />
                <Bar dataKey="count" radius={4}>
                    {buckets.map((bucket) => (
                        <Cell
                            key={bucket.tier}
                            fill={TIER_COLOR[bucket.tier]}
                        />
                    ))}
                </Bar>
            </BarChart>
        </ChartContainer>
    );
}
