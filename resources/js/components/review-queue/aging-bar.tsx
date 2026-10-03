import SegmentedBar from './segmented-bar';
import type { QueueRow } from './types';

/** Pending documents split into the three waiting-time buckets. */
export default function AgingBar({
    rows,
    labels = ['0 to 2 days', '3 to 7 days', '8+ days'],
}: {
    rows: QueueRow[];
    /** Legend text per bucket (fresh, aging, overdue) when a queue uses its own thresholds. */
    labels?: [string, string, string];
}) {
    const count = (tier: QueueRow['tier']) => rows.filter((r) => r.tier === tier).length;

    return (
        <SegmentedBar
            ariaLabel={`Waiting time: ${count('fresh')} at ${labels[0]}, ${count('aging')} at ${labels[1]}, ${count('overdue')} at ${labels[2]}`}
            segments={[
                { label: labels[0], count: count('fresh'), className: 'bg-info' },
                { label: labels[1], count: count('aging'), className: 'bg-warning' },
                { label: labels[2], count: count('overdue'), className: 'bg-destructive' },
            ]}
        />
    );
}
