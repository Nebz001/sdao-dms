import { Badge } from '@/components/ui/badge';
import { cn, statusLabel } from '@/lib/utils';

const statusStyles: Record<string, string> = {
    draft: 'bg-muted text-muted-foreground border-border',
    in_review: 'bg-info/10 text-info-foreground border-info/20',
    returned: 'bg-warning/10 text-warning-foreground border-warning/20',
    approved: 'bg-success/10 text-success-foreground border-success/20',
    rejected: 'bg-destructive/10 text-destructive-foreground border-destructive/20',
};

/**
 * Left-border accent classes keyed by status, sharing `statusStyles`'
 * color mapping so a card's accent border always agrees with its badge.
 * Only meaningful for single-status contexts (a review queue's wrapper
 * card, a document show page) — never a mixed-status list.
 */
const statusBorderStyles: Record<string, string> = {
    draft: 'border-l-muted-foreground',
    in_review: 'border-l-info',
    returned: 'border-l-warning',
    approved: 'border-l-success',
    rejected: 'border-l-destructive',
};

export function statusBorderClass(status: string): string {
    return statusBorderStyles[status] ?? statusBorderStyles.draft;
}

type StatusBadgeProps = {
    status: string;
    className?: string;
};

/**
 * Shared tonal status badge (tinted background + colored text + subtle
 * border) used across every document list/show and review page instead of
 * a per-page `statusVariant` map.
 */
export function StatusBadge({ status, className }: StatusBadgeProps) {
    return (
        <Badge variant="outline" className={cn(statusStyles[status] ?? statusStyles.draft, className)}>
            {statusLabel(status)}
        </Badge>
    );
}
