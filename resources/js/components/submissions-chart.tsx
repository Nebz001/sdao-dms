import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import ChartEmpty from '@/components/chart-empty';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';

export type SubmissionMonth = {
    monthStart: string;
    label: string;
    count: number;
};

type SubmissionsChartProps = {
    months: SubmissionMonth[];
};

const chartConfig = {
    count: { label: 'Submitted', color: 'var(--chart-1)' },
} satisfies ChartConfig;

/**
 * Documents this organization submitted per month, last 8 months — the
 * student-dashboard sibling of ReviewActivityChart, same container/grid/
 * tooltip recipe, one bar instead of a stacked one since there's a single
 * series here (a submission has no "outcome" yet).
 */
export default function SubmissionsChart({ months }: SubmissionsChartProps) {
    const total = months.reduce((sum, m) => sum + m.count, 0);

    if (total === 0) {
        return <ChartEmpty message="No submissions in the last 8 months" />;
    }

    return (
        <>
            <ChartContainer config={chartConfig} className="h-[220px] w-full">
                <BarChart
                    accessibilityLayer
                    data={months}
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
                        content={<ChartTooltipContent hideLabel />}
                    />
                    <Bar
                        dataKey="count"
                        fill="var(--color-count)"
                        radius={[2, 2, 0, 0]}
                    />
                </BarChart>
            </ChartContainer>
            <ul className="sr-only">
                {months.map((m) => (
                    <li key={m.monthStart}>
                        {m.label}: {m.count} submitted
                    </li>
                ))}
            </ul>
        </>
    );
}
