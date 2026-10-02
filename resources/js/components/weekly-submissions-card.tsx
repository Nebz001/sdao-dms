import { Link, router } from '@inertiajs/react';
import { Bar, BarChart, Cell, XAxis } from 'recharts';
import ChartEmpty from '@/components/chart-empty';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

export type WeeklySubmissions = {
    termLabel: string;
    weeks: { label: string; start: string; end: string; count: number; current: boolean; href: string }[];
    thisWeek: number;
    lastWeek: number | null;
    delta: number | null;
};

const chartConfig = {
    count: { label: 'Submitted', color: 'var(--chart-1)' },
} satisfies ChartConfig;

/** Past weeks are a softer shade of the brand series; the current week is the full-strength one. */
const PAST_FILL = 'color-mix(in oklab, var(--chart-1) 55%, var(--background))';
const CURRENT_FILL = 'var(--chart-1)';

/**
 * "5 more than last week", colored with the existing up, down and no-change
 * tokens. The words carry the meaning; the color only reinforces it.
 */
function changeLine(delta: number): { text: string; className: string } {
    if (delta > 0) {
        return { text: `${delta} more than last week`, className: 'text-success-foreground' };
    }

    if (delta < 0) {
        return { text: `${Math.abs(delta)} fewer than last week`, className: 'text-destructive-foreground' };
    }

    return { text: 'Same as last week', className: 'text-muted-foreground' };
}

/**
 * Submissions per week across the current term, all form types, resubmissions
 * not counted. Each bar opens the Activity Log for that week; the list under
 * the chart gives keyboard and screen reader users the same links as text.
 */
export default function WeeklySubmissionsCard({ data }: { data: WeeklySubmissions }) {
    const change = data.delta === null ? null : changeLine(data.delta);
    const total = data.weeks.reduce((sum, w) => sum + w.count, 0);

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-3">
                <div className="grid gap-1.5">
                    <CardTitle role="heading" aria-level={2} className="text-base">Submissions per week</CardTitle>
                    <CardDescription>{data.termLabel}, all form types</CardDescription>
                </div>
                {data.weeks.length > 0 && (
                    <div className="text-right">
                        <p className="text-3xl leading-none font-semibold tabular-nums">{data.thisWeek}</p>
                        {change && <p className={cn('mt-1 text-xs font-medium', change.className)}>{change.text}</p>}
                    </div>
                )}
            </CardHeader>
            <CardContent>
                {data.weeks.length === 0 ? (
                    <ChartEmpty message="This term has not started yet" />
                ) : total === 0 ? (
                    <ChartEmpty message="No submissions yet this term" />
                ) : (
                    <>
                        <ChartContainer config={chartConfig} className="h-[200px] w-full">
                            <BarChart accessibilityLayer data={data.weeks} margin={{ left: 0, right: 0 }}>
                                <XAxis
                                    dataKey="label"
                                    tickLine={false}
                                    axisLine={false}
                                    tickMargin={8}
                                    interval={0}
                                    fontSize={12}
                                />
                                <ChartTooltip
                                    cursor={false}
                                    content={<ChartTooltipContent indicator="dot" />}
                                />
                                <Bar
                                    dataKey="count"
                                    radius={[4, 4, 0, 0]}
                                    className="cursor-pointer"
                                    onClick={(week: { payload?: { href?: string } }) => {
                                        if (week.payload?.href) {
                                            router.visit(week.payload.href);
                                        }
                                    }}
                                >
                                    {data.weeks.map((week) => (
                                        <Cell key={week.start} fill={week.current ? CURRENT_FILL : PAST_FILL} />
                                    ))}
                                </Bar>
                            </BarChart>
                        </ChartContainer>
                        <ul className="sr-only focus-within:not-sr-only focus-within:mt-3 focus-within:flex focus-within:flex-wrap focus-within:gap-x-4 focus-within:gap-y-1 focus-within:text-xs">
                            {data.weeks.map((week) => (
                                <li key={week.start}>
                                    <Link href={week.href} className="underline">
                                        {week.label}, week of {week.start}: {week.count} submitted
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </>
                )}
            </CardContent>
        </Card>
    );
}

/** Same card shape while the deferred weekly data loads. */
export function WeeklySubmissionsSkeleton() {
    return (
        <Card aria-busy="true">
            <CardHeader>
                <CardTitle role="heading" aria-level={2} className="text-base">Submissions per week</CardTitle>
            </CardHeader>
            <CardContent>
                <Skeleton className="h-[200px] w-full" />
            </CardContent>
        </Card>
    );
}
