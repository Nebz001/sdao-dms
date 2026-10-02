import { Link } from '@inertiajs/react';
import MeterBar from '@/components/meter-bar';
import type { MeterTone } from '@/components/meter-bar';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

export type ReturnAnalytics = {
    reasons: {
        sample: number;
        minimum: number;
        enough: boolean;
        rows: { label: string; count: number; percent: number }[];
        href: string;
    };
    rates: {
        minimum: number;
        rows: {
            formType: string;
            label: string;
            submitted: number;
            returned: number;
            enough: boolean;
            percent: number | null;
            href: string;
        }[];
        average: { value: number | null; sample: number; minimum: number; enough: boolean };
    };
};

/** A section flagged by this share of returns: amber when it is common, blue otherwise. */
function reasonTone(percent: number): MeterTone {
    return percent >= 30 ? 'warning' : 'info';
}

/** Return rate tiers: 50 percent or more is red, 30 amber, 15 blue, below that green. The percent is always printed. */
function rateTone(percent: number): MeterTone {
    if (percent >= 50) {
        return 'destructive';
    }

    if (percent >= 30) {
        return 'warning';
    }

    return percent >= 15 ? 'info' : 'success';
}

function NotEnoughData({ children }: { children: string }) {
    return (
        <div className="rounded-lg border border-dashed p-4 text-center">
            <p className="text-sm font-medium">Not enough data yet</p>
            <p className="mt-1 text-xs text-muted-foreground">{children}</p>
        </div>
    );
}

function WhyReturnedCard({ reasons }: { reasons: ReturnAnalytics['reasons'] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle role="heading" aria-level={2} className="text-base">Why documents get returned</CardTitle>
                <CardDescription>
                    Sections flagged most often by approvers. High numbers usually mean the form or
                    the guidance is unclear, not that one org is careless.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {reasons.enough ? (
                    <ul className="flex flex-col gap-3">
                        {reasons.rows.map((row) => (
                            <li key={row.label}>
                                <Link
                                    href={reasons.href}
                                    className="block rounded-sm focus-visible:focus-ring-edge"
                                >
                                    <span className="flex items-baseline justify-between gap-3 text-sm">
                                        <span className="min-w-0 break-words" title={row.label}>
                                            {row.label}
                                        </span>
                                        <span className="font-semibold tabular-nums">{row.percent}%</span>
                                    </span>
                                    <MeterBar
                                        value={row.percent}
                                        tone={reasonTone(row.percent)}
                                        label={`${row.label}: flagged in ${row.percent}% of returns`}
                                        className="mt-1.5"
                                    />
                                </Link>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <NotEnoughData>
                        {`Needs ${reasons.minimum} returned documents with flagged sections. ${reasons.sample} so far this academic year.`}
                    </NotEnoughData>
                )}
            </CardContent>
        </Card>
    );
}

function ReturnRateCard({ rates }: { rates: ReturnAnalytics['rates'] }) {
    const { average } = rates;

    return (
        <Card>
            <CardHeader>
                <CardTitle role="heading" aria-level={2} className="text-base">Return rate by form type</CardTitle>
                <CardDescription>Share of submissions sent back at least once</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-5">
                <ul className="flex flex-col gap-3">
                    {rates.rows.map((row) => (
                        <li key={row.formType}>
                            <Link
                                href={row.href}
                                className="grid grid-cols-[minmax(0,11rem)_1fr_3.5rem] items-center gap-3 rounded-sm text-sm focus-visible:focus-ring-edge"
                            >
                                <span className="break-words" title={row.label}>
                                    {row.label}
                                </span>
                                {row.percent !== null ? (
                                    <>
                                        <MeterBar
                                            value={row.percent}
                                            tone={rateTone(row.percent)}
                                            label={`${row.label}: ${row.percent}% of submissions returned`}
                                        />
                                        <span className="text-right font-semibold tabular-nums">
                                            {row.percent}%
                                        </span>
                                    </>
                                ) : (
                                    <span
                                        className="col-span-2 text-xs text-muted-foreground"
                                        title={`Needs ${rates.minimum} submissions. ${row.submitted} so far.`}
                                    >
                                        Not enough data yet
                                    </span>
                                )}
                            </Link>
                        </li>
                    ))}
                </ul>

                <div className="flex items-baseline gap-3 border-t pt-4">
                    {average.enough && average.value !== null ? (
                        <>
                            <span className="font-mono text-2xl font-semibold tabular-nums">
                                {average.value.toFixed(1)}
                            </span>
                            <span className="text-sm text-muted-foreground">
                                average submissions before a document is approved
                            </span>
                        </>
                    ) : (
                        <span className="text-sm text-muted-foreground">
                            Not enough data yet for the average number of submissions before a
                            document is approved. Needs {average.minimum} approved documents,{' '}
                            {average.sample} so far.
                        </span>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

/** The two return-analytics cards side by side. */
export default function ReturnAnalyticsRow({ data }: { data: ReturnAnalytics }) {
    return (
        <div className="grid gap-4 md:grid-cols-2 [&>*]:min-w-0">
            <WhyReturnedCard reasons={data.reasons} />
            <ReturnRateCard rates={data.rates} />
        </div>
    );
}

/** Shown while the deferred analytics load, in the same two-card shape. */
export function ReturnAnalyticsSkeleton() {
    return (
        <div className="grid gap-4 md:grid-cols-2 [&>*]:min-w-0" aria-busy="true">
            {['Why documents get returned', 'Return rate by form type'].map((title) => (
                <Card key={title}>
                    <CardHeader>
                        <CardTitle role="heading" aria-level={2} className="text-base">{title}</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        {Array.from({ length: 5 }).map((_, i) => (
                            <Skeleton key={i} className="h-4 w-full" />
                        ))}
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
