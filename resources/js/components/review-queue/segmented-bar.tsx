import { cn } from '@/lib/utils';

export type BarSegment = {
    label: string;
    count: number;
    /** Tailwind background token class, e.g. "bg-success". */
    className: string;
};

/**
 * A thin bar split into proportional segments, with a legend (swatch, count,
 * label) underneath. Shared by the aging bar and the approved/returned bar.
 * The legend carries every number, so color is never the only signal.
 */
export default function SegmentedBar({
    segments,
    ariaLabel,
}: {
    segments: BarSegment[];
    ariaLabel: string;
}) {
    const total = segments.reduce((sum, s) => sum + s.count, 0);

    return (
        <div className="flex flex-col gap-3">
            <div role="img" aria-label={ariaLabel} className="flex h-1.5 gap-0.5 overflow-hidden rounded-full bg-muted">
                {total > 0 &&
                    segments
                        .filter((s) => s.count > 0)
                        .map((s) => (
                            <div key={s.label} className={cn('h-full', s.className)} style={{ flexGrow: s.count }} />
                        ))}
            </div>
            <SegmentedLegend segments={segments} />
        </div>
    );
}

/**
 * The legend under a segmented bar: items side by side and spread evenly,
 * each a square marker and a bold count over a small muted label. Stays on
 * one row when it fits and wraps cleanly when it does not. Exported so a
 * card that draws its own bar still gets the identical legend.
 */
export function SegmentedLegend({ segments }: { segments: BarSegment[] }) {
    return (
        <ul className="flex flex-wrap gap-x-5 gap-y-2">
            {segments.map((s) => (
                <li key={s.label} className="flex min-w-20 flex-1 flex-col">
                    <span className="flex items-center gap-1.5 text-lg leading-tight font-semibold tabular-nums">
                        <span className={cn('size-2 shrink-0 rounded-[2px]', s.className)} aria-hidden />
                        {s.count}
                    </span>
                    <span className="text-xs break-words text-muted-foreground">{s.label}</span>
                </li>
            ))}
        </ul>
    );
}
