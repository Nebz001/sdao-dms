import { Link } from '@inertiajs/react';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type StatRow = {
    key: string | number;
    label: string;
    href: string;
    /** Shown at the row's end, e.g. a per-form-type queue count. */
    count?: number;
    /** Shown at the row's end, rendered via StatusBadge, e.g. a document's status. */
    status?: string;
    /** Shown at the row's end for non-status badges. Ignored when `status` is set. */
    badge?: string;
};

type DashboardStatCardProps = {
    title: string;
    /** The card's headline number, shown under the title. Omit to skip it. */
    headlineCount?: number;
    rows: StatRow[];
    emptyLabel: string;
    viewAllHref?: string;
    viewAllLabel?: string;
};

/**
 * Shared dashboard card: a title, an optional headline count, and a list of
 * linked rows — reused across every role-specific section (Phase 2 item 11
 * Group B) instead of a one-off layout per section.
 */
export default function DashboardStatCard({
    title,
    headlineCount,
    rows,
    emptyLabel,
    viewAllHref,
    viewAllLabel = 'View all',
}: DashboardStatCardProps) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-2 space-y-0">
                <div>
                    <CardTitle className="text-base">{title}</CardTitle>
                    {headlineCount !== undefined && (
                        <p className="mt-1 text-2xl font-semibold tabular-nums">{headlineCount}</p>
                    )}
                </div>
                {viewAllHref && rows.length > 0 && (
                    <Link href={viewAllHref} className="text-sm text-muted-foreground hover:text-foreground">
                        {viewAllLabel}
                    </Link>
                )}
            </CardHeader>
            <CardContent className={rows.length > 0 ? 'divide-y' : undefined}>
                {rows.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{emptyLabel}</p>
                ) : (
                    rows.map((row) => (
                        <div key={row.key} className="flex items-center justify-between gap-2 py-2.5 first:pt-0 last:pb-0">
                            <Link href={row.href} className="text-sm font-medium hover:underline">
                                {row.label}
                            </Link>
                            <div className="flex items-center gap-2">
                                {row.status ? (
                                    <StatusBadge status={row.status} />
                                ) : (
                                    row.badge && <Badge variant="secondary">{row.badge}</Badge>
                                )}
                                {row.count !== undefined && (
                                    <span className="text-sm text-muted-foreground tabular-nums">{row.count}</span>
                                )}
                            </div>
                        </div>
                    ))
                )}
            </CardContent>
        </Card>
    );
}
