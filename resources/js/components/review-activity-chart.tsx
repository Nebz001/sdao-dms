import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import ChartEmpty from '@/components/chart-empty';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';

type ReviewWeek = {
    weekStart: string;
    label: string;
    approved: number;
    returned: number;
    rejected: number;
    total: number;
};

type ReviewActivityChartProps = {
    weeks: ReviewWeek[];
};

const chartConfig = {
    approved: { label: 'Approved', color: 'var(--success)' },
    returned: { label: 'Returned', color: 'var(--warning)' },
    rejected: { label: 'Rejected', color: 'var(--destructive)' },
} satisfies ChartConfig;

/**
 * Documents this approver reviewed per week, last 8 weeks — a stacked bar
 * per week (approved/returned/rejected), same semantic colors as every
 * other status/action indicator in the app. `accessibilityLayer` is this
 * codebase's existing chart-accessibility convention (see
 * proposal-funnel-chart.tsx); the sr-only list below it is the one addition
 * this chart needs beyond that, since a stacked bar chart has no other
 * textual summary of its own (unlike the donut/split charts, whose legend
 * already doubles as one).
 */
export default function ReviewActivityChart({
    weeks,
}: ReviewActivityChartProps) {
    const total = weeks.reduce((sum, w) => sum + w.total, 0);

    if (total === 0) {
        return <ChartEmpty message="No reviews in the last 8 weeks" />;
    }

    return (
        <>
            <ChartContainer config={chartConfig} className="h-[220px] w-full">
                <BarChart
                    accessibilityLayer
                    data={weeks}
                    margin={{ left: -20 }}
                >
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
                        content={<ChartTooltipContent indicator="dot" />}
                    />
                    <Bar
                        dataKey="approved"
                        stackId="week"
                        fill="var(--color-approved)"
                        radius={[0, 0, 2, 2]}
                    />
                    <Bar
                        dataKey="returned"
                        stackId="week"
                        fill="var(--color-returned)"
                        radius={[0, 0, 0, 0]}
                    />
                    <Bar
                        dataKey="rejected"
                        stackId="week"
                        fill="var(--color-rejected)"
                        radius={[2, 2, 0, 0]}
                    />
                </BarChart>
            </ChartContainer>
            <ul className="sr-only">
                {weeks.map((w) => (
                    <li key={w.weekStart}>
                        Week of {w.label}: {w.approved} approved, {w.returned}{' '}
                        returned, {w.rejected} rejected
                    </li>
                ))}
            </ul>
        </>
    );
}
