import { Bar, BarChart, XAxis, YAxis } from 'recharts';
import type { DefaultTooltipContentProps, TooltipProps } from 'recharts';
import { ChartContainer, ChartTooltip } from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';

type FunnelStep = { role: string; count: number };

type FunnelGroup = {
    variant: string;
    label: string;
    total: number;
    steps: FunnelStep[];
};

type ProposalFunnelChartProps = {
    groups: FunnelGroup[];
};

type ChartRow = { variant: string; total: number } & Record<string, number | string>;

/**
 * One shade of `--info` per step position, not a rainbow palette — every
 * step is still, by meaning, an "in review" proposal (the same blue used by
 * the status pie and StatusBadge elsewhere on this dashboard). The shade
 * lightens with position so a multi-step row still reads left-to-right as
 * a progression even though every segment is fundamentally the same color
 * family. `color-mix` is already an established pattern in this codebase
 * (see `--brand-hover` in app.css).
 */
function stepColor(index: number): string {
    const mixPercent = Math.max(35, 100 - index * 18);

    return `color-mix(in oklab, var(--info) ${mixPercent}%, var(--background))`;
}

const chartConfig = {
    count: { label: 'Proposals', color: 'var(--info)' },
} satisfies ChartConfig;

/**
 * Custom Y-axis tick, replacing Recharts' default category label, for three
 * reasons: (1) the default right-anchors each label against the axis line,
 * so shorter labels visually "float" at a different left edge than longer
 * ones instead of forming a consistent column — this renders every label
 * left-anchored at a fixed x instead; (2) ChartContainer's own CSS
 * (`[&_.recharts-cartesian-axis-tick_text]:fill-muted-foreground`) applies
 * to every axis tick via a stylesheet rule, which beats a plain SVG `fill`
 * attribute in the cascade — only an inline `style` wins over it, hence
 * `style={{ fill: ... }}` here rather than a `fill` prop; (3) every variant
 * label in this data set has exactly one comma ("Regular, Off-Calendar"),
 * so splitting there gives a deliberate, consistent two-line wrap instead
 * of whatever Recharts' default text measurement produces.
 */
// Fixed left inset for every label, deliberately ignoring the `x` Recharts
// passes in (which is the axis-line position — the right edge of the
// reserved label column, where the bars start). Anchoring there with
// `textAnchor="start"` was what made labels run rightward into the bars
// instead of forming a left-aligned column.
const LABEL_X = 4;

function VariantTick({
    y = 0,
    payload,
}: {
    y?: number;
    payload?: { value?: string };
}) {
    const value = payload?.value ?? '';
    const [firstLine, ...rest] = value.split(', ');
    const secondLine = rest.join(', ');
    const tickStyle: React.CSSProperties = {
        fill: 'var(--foreground)',
        fontSize: 13,
        fontWeight: 500,
    };

    return (
        <text x={LABEL_X} y={y} textAnchor="start" style={tickStyle}>
            {secondLine ? (
                <>
                    <tspan x={LABEL_X} dy={-4}>
                        {firstLine},
                    </tspan>
                    <tspan x={LABEL_X} dy={14}>
                        {secondLine}
                    </tspan>
                </>
            ) : (
                <tspan x={LABEL_X} dy={4}>
                    {firstLine}
                </tspan>
            )}
        </text>
    );
}

/**
 * Custom tooltip content (not the shared ChartTooltipContent) because the
 * data shape here is genuinely different from every other chart: each row
 * (chain variant) has its own sparse set of step columns, so a hovered row
 * needs to skip the columns that don't apply to it and look up each
 * column's actual role name from the row itself rather than a shared
 * config. Visual chrome (border, shadow, spacing) is copied verbatim from
 * ChartTooltipContent so it still reads as the same tooltip style as every
 * other chart on the dashboard.
 */
function FunnelTooltipContent({
    active,
    payload,
    label,
}: TooltipProps<number, string> &
    Omit<DefaultTooltipContentProps<number, string>, 'accessibilityLayer'>) {
    if (!active || !payload?.length) {
        return null;
    }

    const rows = payload.filter(
        (item) => typeof item.value === 'number' && item.value > 0,
    );

    if (rows.length === 0) {
        return null;
    }

    return (
        <div className="grid min-w-[10rem] items-start gap-1.5 rounded-lg border border-border/50 bg-background px-2.5 py-1.5 text-xs shadow-xl">
            <div className="font-medium">{label}</div>
            <div className="grid gap-1.5">
                {rows.map((item) => {
                    const dataKey = String(item.dataKey ?? '');
                    const row = item.payload as ChartRow;
                    const role = row[`${dataKey}_role`];

                    return (
                        <div
                            key={dataKey}
                            className="flex w-full items-center gap-2"
                        >
                            <div
                                className="h-2.5 w-2.5 shrink-0 rounded-[2px]"
                                style={{ backgroundColor: item.color }}
                            />
                            <div className="flex flex-1 items-center justify-between gap-3">
                                <span className="text-muted-foreground">
                                    {typeof role === 'string' ? role : ''}
                                </span>
                                <span className="font-mono font-medium text-foreground tabular-nums">
                                    {item.value}
                                </span>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

/**
 * A real shadcn horizontal Bar Chart (`layout="vertical"`, one category row
 * per chain variant) instead of a literal funnel/wedge chart — the wedge
 * version grew far too tall once more than one or two chain variants had
 * proposals in review, since every variant needed its own full-size chart
 * stacked underneath the last. This is one compact chart total: each row
 * is a single bar, segmented (stacked) when its variant has proposals at
 * more than one step, ordered left-to-right in chain order so the row
 * still reads as a funnel progression.
 */
export default function ProposalFunnelChart({
    groups,
}: ProposalFunnelChartProps) {
    const maxSteps = Math.max(0, ...groups.map((g) => g.steps.length));

    const chartData: ChartRow[] = groups.map((group) => {
        // No "· total" suffix — the bar itself already communicates the
        // value, and the full breakdown is one hover away in the tooltip.
        const row: ChartRow = {
            variant: group.label,
            total: group.total,
        };

        group.steps.forEach((step, index) => {
            row[`step_${index}`] = step.count;
            row[`step_${index}_role`] = step.role;
        });

        return row;
    });

    return (
        <ChartContainer
            config={chartConfig}
            className="w-full"
            style={{ height: Math.max(140, groups.length * 72) }}
        >
            <BarChart
                accessibilityLayer
                data={chartData}
                layout="vertical"
                margin={{ left: 8 }}
            >
                <XAxis type="number" hide />
                <YAxis
                    dataKey="variant"
                    type="category"
                    tickLine={false}
                    axisLine={false}
                    tickMargin={8}
                    width={160}
                    tick={<VariantTick />}
                />
                <ChartTooltip cursor={false} content={<FunnelTooltipContent />} />
                {Array.from({ length: maxSteps }, (_, index) => (
                    <Bar
                        key={index}
                        dataKey={`step_${index}`}
                        stackId="funnel"
                        fill={stepColor(index)}
                        radius={4}
                        barSize={28}
                    />
                ))}
            </BarChart>
        </ChartContainer>
    );
}
