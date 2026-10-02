import { useState } from 'react';
import { Bar, BarChart, Cell, XAxis, YAxis } from 'recharts';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer } from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

/** Up to this many variants read as a toggle; more would crowd the header, so they become a menu. */
const TOGGLE_MAX_VARIANTS = 3;

export type FunnelVariant = {
    variant: string;
    label: string;
    submitted: number;
    steps: { label: string; count: number }[];
    approved: number;
};

const chartConfig = {
    count: { label: 'Proposals', color: 'var(--chart-1)' },
} satisfies ChartConfig;

const ROW_HEIGHT = 34;

/** Inline style, not a fill attribute: ChartContainer's own tick CSS wins over attributes. */
const LABEL_STYLE: React.CSSProperties = { fill: 'var(--foreground)', fontSize: 14, fontWeight: 500 };
const COUNT_STYLE: React.CSSProperties = { fill: 'var(--foreground)', fontSize: 14, fontWeight: 600 };

/** Wraps a long label onto two lines at the space nearest its middle, e.g. "Asst. Director of Academic Services". */
function splitLabel(value: string): [string, string?] {
    if (value.length <= 22) {
        return [value];
    }

    const middle = value.length / 2;
    const spaces = [...value.matchAll(/ /g)].map((m) => m.index ?? 0);
    const at = spaces.reduce((best, i) => (Math.abs(i - middle) < Math.abs(best - middle) ? i : best), spaces[0] ?? -1);

    return at < 0 ? [value] : [value.slice(0, at), value.slice(at + 1)];
}

function LabelTick({ y = 0, payload }: { y?: number; payload?: { value?: string } }) {
    const [first, second] = splitLabel(payload?.value ?? '');

    return (
        <text x={4} y={y} textAnchor="start" style={LABEL_STYLE}>
            <tspan x={4} dy={second ? -2 : 5}>
                {first}
            </tspan>
            {second && (
                <tspan x={4} dy={15}>
                    {second}
                </tspan>
            )}
        </text>
    );
}

/**
 * The right-hand count column. A second, category Y axis on the right shares
 * the row positions with the label axis, so every count lands in one
 * right-aligned column no matter how long the bar is.
 */
function countTick(counts: Map<string, number>) {
    return function CountTick({ x = 0, y = 0, payload }: { x?: number | string; y?: number | string; payload?: { value?: string | number } }) {
        return (
            <text x={Number(x) + 10} y={Number(y)} dy={5} textAnchor="start" style={COUNT_STYLE}>
                {counts.get(String(payload?.value ?? '')) ?? 0}
            </text>
        );
    };
}

function FunnelBars({ funnel }: { funnel: FunnelVariant }) {
    const rows = [
        { label: 'Submitted', count: funnel.submitted, fill: 'var(--chart-1)' },
        ...funnel.steps.map((step) => ({ label: step.label, count: step.count, fill: 'var(--chart-1)' })),
        { label: 'Approved', count: funnel.approved, fill: 'var(--success)' },
    ];
    const counts = new Map(rows.map((row) => [row.label, row.count]));

    return (
        <ChartContainer
            config={chartConfig}
            className="w-full"
            style={{ height: rows.length * ROW_HEIGHT }}
        >
            <BarChart
                accessibilityLayer
                data={rows}
                layout="vertical"
                margin={{ left: 0, right: 0, top: 0, bottom: 0 }}
                barCategoryGap={8}
            >
                <XAxis type="number" hide domain={[0, Math.max(1, funnel.submitted)]} />
                <YAxis
                    yAxisId="labels"
                    dataKey="label"
                    type="category"
                    tickLine={false}
                    axisLine={false}
                    width={165}
                    tick={<LabelTick />}
                    interval={0}
                />
                <YAxis
                    yAxisId="counts"
                    orientation="right"
                    dataKey="label"
                    type="category"
                    tickLine={false}
                    axisLine={false}
                    width={36}
                    tick={countTick(counts)}
                    interval={0}
                />
                <Bar
                    yAxisId="labels"
                    dataKey="count"
                    radius={4}
                    barSize={16}
                    background={{ fill: 'var(--muted)', radius: 4 }}
                >
                    {rows.map((row) => (
                        <Cell key={row.label} fill={row.fill} />
                    ))}
                </Bar>
            </BarChart>
        </ChartContainer>
    );
}

/**
 * Stage-by-stage counts for one chain variant at a time. Step position is not
 * comparable across variants, so the toggle is built from the variants that
 * actually have proposals this academic year, and each variant draws its own
 * real steps. Every row is labeled and counted as text; the Approved bar is
 * the only green one.
 */
export default function ProposalFunnelCard({ funnels }: { funnels: FunnelVariant[] }) {
    const [selected, setSelected] = useState(funnels[0]?.variant ?? '');
    const funnel = funnels.find((f) => f.variant === selected) ?? funnels[0];

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-3">
                <div className="grid gap-1.5">
                    <CardTitle role="heading" aria-level={2} className="text-base">Activity proposal funnel</CardTitle>
                    <CardDescription>
                        {funnel
                            ? `${funnel.label} chain. Step position is not comparable across variants.`
                            : 'No activity proposals were submitted this academic year.'}
                    </CardDescription>
                </div>
                {funnels.length > TOGGLE_MAX_VARIANTS && (
                    <Select value={funnel?.variant} onValueChange={setSelected}>
                        <SelectTrigger size="sm" className="w-52 shrink-0" aria-label="Chain variant">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {funnels.map((f) => (
                                <SelectItem key={f.variant} value={f.variant}>
                                    {f.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                )}
                {funnels.length > 1 && funnels.length <= TOGGLE_MAX_VARIANTS && (
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        size="sm"
                        value={funnel?.variant}
                        onValueChange={(value) => value && setSelected(value)}
                        aria-label="Chain variant"
                        className="max-w-[55%] flex-wrap justify-end"
                    >
                        {funnels.map((f) => (
                            <ToggleGroupItem key={f.variant} value={f.variant} className="text-xs">
                                {f.label}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                )}
            </CardHeader>
            {funnel && (
                <CardContent>
                    <FunnelBars funnel={funnel} />
                </CardContent>
            )}
        </Card>
    );
}
