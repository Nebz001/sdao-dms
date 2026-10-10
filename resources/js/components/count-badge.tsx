import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

/**
 * A small count chip for a group header or tab. `blue` is the "there is
 * something waiting" state of a queue header; it falls back to the neutral
 * chip at zero when the caller passes `quietWhenZero`.
 */
export default function CountBadge({
    count,
    variant = 'secondary',
    quietWhenZero = false,
}: {
    count: number;
    variant?: 'secondary' | 'outline' | 'blue';
    quietWhenZero?: boolean;
}) {
    const blue = variant === 'blue' && !(quietWhenZero && count === 0);

    return (
        <Badge
            variant={variant === 'outline' ? 'outline' : 'secondary'}
            className={cn(
                'tabular-nums',
                blue && 'border-transparent bg-blue-500/15 text-blue-700 dark:bg-blue-400/20 dark:text-blue-300',
            )}
        >
            {count}
        </Badge>
    );
}
