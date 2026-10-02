import { Link } from '@inertiajs/react';
import MeterBar from '@/components/meter-bar';
import PageNotice from '@/components/page-notice';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

export type OrgCompliance = {
    renewalSeason: boolean;
    /** The academic year the meter is measured against, e.g. "2027-2028". */
    targetYear: string;
    done: number;
    total: number;
    pendingTotal: number;
    pending: { organizationId: number; organizationName: string; count: number; href: string }[];
    viewAllHref: string;
};

/** "2026-2027" as the dashboard writes it: "2026 to 2027". */
export function academicYearLabel(academicYear: string): string {
    return academicYear.replace('-', ' to ');
}

/**
 * Organization compliance: how many approved organizations are covered, and
 * which have documents still open. Outside renewal season the meter is
 * measured against the current academic year; in 3rd term it switches to next
 * year and a notice makes the season hard to miss.
 */
export default function OrgComplianceCard({ data }: { data: OrgCompliance }) {
    const percent = data.total > 0 ? Math.round((data.done / data.total) * 100) : 0;
    const year = academicYearLabel(data.targetYear);
    const allDone = data.total > 0 && data.done === data.total;

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-2">
                <div className="grid gap-1.5">
                    <CardTitle className="text-base">Organization compliance</CardTitle>
                    <CardDescription>Renewal opens when the term is set to 3rd</CardDescription>
                </div>
                <Link href={data.viewAllHref} className="shrink-0 text-sm text-primary-text hover:underline">
                    View all
                </Link>
            </CardHeader>
            <CardContent className="flex flex-col gap-5">
                {data.renewalSeason && (
                    <PageNotice tone="warning">
                        Renewal season is open. Organizations can renew for A.Y. {year} now.
                    </PageNotice>
                )}

                <div>
                    {data.total === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No organization has been approved yet, so there is nothing to renew.
                        </p>
                    ) : (
                        <>
                            <div className="flex items-baseline justify-between gap-3 text-sm">
                                <span>
                                    {data.renewalSeason ? `Renewed for A.Y. ${year}` : 'Renewed this academic year'}
                                </span>
                                <span className="font-semibold tabular-nums">
                                    {data.done} / {data.total}
                                </span>
                            </div>
                            <MeterBar
                                value={percent}
                                tone="success"
                                label={`${data.done} of ${data.total} organizations covered for A.Y. ${year}`}
                                className="mt-2"
                            />
                            {allDone && (
                                <p className="mt-2 text-xs text-muted-foreground">
                                    All caught up. Every organization has renewed for A.Y. {year}.
                                </p>
                            )}
                        </>
                    )}
                </div>

                <div>
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Organizations with pending items
                    </p>
                    {data.pending.length === 0 ? (
                        <p className="mt-2 text-sm text-muted-foreground">
                            Nothing pending. No organization has a draft, in-review, or returned document
                            right now.
                        </p>
                    ) : (
                        <>
                            <ul className="mt-2 flex flex-col gap-2">
                                {data.pending.map((org) => (
                                    <li key={org.organizationId}>
                                        <Link
                                            href={org.href}
                                            className="flex items-center justify-between gap-3 rounded-lg border bg-muted/40 px-3 py-2 text-sm transition-colors hover:border-primary-text/40 focus-visible:focus-ring-edge"
                                        >
                                            <span className="min-w-0 truncate font-semibold" title={org.organizationName}>
                                                {org.organizationName}
                                            </span>
                                            <span className="shrink-0 text-xs font-medium text-warning-foreground">
                                                {org.count} pending
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                            {data.pendingTotal > data.pending.length && (
                                <p className="mt-2 text-xs text-muted-foreground">
                                    and {data.pendingTotal - data.pending.length} more organizations
                                </p>
                            )}
                        </>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
