import { cn } from '@/lib/utils';

type ChartEmptyProps = {
    message?: string;
    className?: string;
};

/**
 * Shown instead of a chart when there's no data to plot — a short message
 * in a fixed-height box, not a broken/blank chart. The height matches this
 * dashboard's other small charts so an empty card doesn't visibly shrink
 * next to a populated one in the same grid row.
 */
export default function ChartEmpty({
    message = 'No data for this academic year yet',
    className,
}: ChartEmptyProps) {
    return (
        <div
            className={cn(
                'flex h-[180px] items-center justify-center text-center text-sm text-muted-foreground',
                className,
            )}
        >
            {message}
        </div>
    );
}
