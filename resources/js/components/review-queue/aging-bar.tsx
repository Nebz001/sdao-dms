import SegmentedBar from './segmented-bar';
import type { QueueRow } from './types';

/** Pending documents split into the three waiting-time buckets. */
export default function AgingBar({ rows }: { rows: QueueRow[] }) {
    const count = (tier: QueueRow['tier']) => rows.filter((r) => r.tier === tier).length;

    return (
        <SegmentedBar
            ariaLabel={`Waiting time: ${count('fresh')} at 0 to 2 days, ${count('aging')} at 3 to 7 days, ${count('overdue')} at 8 or more days`}
            segments={[
                { label: '0 to 2 days', count: count('fresh'), className: 'bg-info' },
                { label: '3 to 7 days', count: count('aging'), className: 'bg-warning' },
                { label: '8+ days', count: count('overdue'), className: 'bg-destructive' },
            ]}
        />
    );
}
