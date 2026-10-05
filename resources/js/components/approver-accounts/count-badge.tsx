import { Badge } from '@/components/ui/badge';

/** A small count chip for a group header or tab. */
export default function CountBadge({
    count,
    variant = 'secondary',
}: {
    count: number;
    variant?: 'secondary' | 'outline';
}) {
    return (
        <Badge variant={variant} className="tabular-nums">
            {count}
        </Badge>
    );
}
