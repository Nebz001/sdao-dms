import { cn } from '@/lib/utils';

/** A thin determinate bar on the muted track; the fill color is the caller's token class. */
export default function ThinProgress({
    value,
    max,
    label,
    className,
    fillClassName,
}: {
    value: number;
    max: number;
    label: string;
    className?: string;
    /** Tailwind background token class for the fill, e.g. "bg-success". */
    fillClassName: string;
}) {
    const percent = max > 0 ? Math.min(100, Math.round((value / max) * 100)) : 0;

    return (
        <div
            role="progressbar"
            aria-label={label}
            aria-valuemin={0}
            aria-valuemax={max}
            aria-valuenow={value}
            className={cn('h-1.5 w-full overflow-hidden rounded-full bg-muted', className)}
        >
            <div className={cn('h-full rounded-full', fillClassName)} style={{ width: `${percent}%` }} />
        </div>
    );
}
