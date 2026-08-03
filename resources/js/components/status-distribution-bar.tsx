import { statusLabel } from '@/lib/utils';

type StatusCount = {
    status: string;
    count: number;
};

type StatusDistributionBarProps = {
    data: StatusCount[];
};

/**
 * Fixed status → color mapping, reusing StatusBadge's exact semantic tokens
 * (see status-badge.tsx's statusBorderStyles) so this bar never introduces a
 * second color meaning for the same five statuses. Order is fixed —
 * Draft → In Review → Returned → Approved → Rejected — and never re-sorted
 * by count, so the color at a given position always means the same status.
 */
const STATUS_FILL: Record<string, string> = {
    draft: 'bg-muted-foreground',
    in_review: 'bg-info',
    returned: 'bg-warning',
    approved: 'bg-success',
    rejected: 'bg-destructive',
};

const STATUS_ORDER = ['draft', 'in_review', 'returned', 'approved', 'rejected'];

/**
 * Hand-rolled stacked bar (no charting library exists in this project — see
 * investigation notes). A 2px surface gap between segments (via flex `gap`
 * over the card's own background, not a border) keeps adjacent same-family
 * hues distinguishable without relying on color alone; the legend below
 * pairs every swatch with its status label and count, never color-only.
 */
export default function StatusDistributionBar({ data }: StatusDistributionBarProps) {
    const byStatus = new Map(data.map((d) => [d.status, d.count]));
    const total = data.reduce((sum, d) => sum + d.count, 0);
    const segments = STATUS_ORDER.map((status) => ({ status, count: byStatus.get(status) ?? 0 })).filter(
        (s) => s.count > 0,
    );

    if (total === 0) {
        return <p className="text-sm text-muted-foreground">No documents in this academic year yet.</p>;
    }

    return (
        <div className="space-y-3">
            <div className="flex h-3 gap-0.5 overflow-hidden rounded-full bg-muted">
                {segments.map((s) => (
                    <div
                        key={s.status}
                        className={STATUS_FILL[s.status]}
                        style={{ flex: s.count }}
                        title={`${statusLabel(s.status)}: ${s.count}`}
                    />
                ))}
            </div>

            <div className="flex flex-wrap gap-x-4 gap-y-1.5">
                {STATUS_ORDER.map((status) => {
                    const count = byStatus.get(status) ?? 0;

                    return (
                        <div key={status} className="flex items-center gap-1.5 text-sm">
                            <span className={`size-2.5 rounded-[2px] ${STATUS_FILL[status]}`} aria-hidden />
                            <span className="text-muted-foreground">{statusLabel(status)}</span>
                            <span className="font-medium tabular-nums">{count}</span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
