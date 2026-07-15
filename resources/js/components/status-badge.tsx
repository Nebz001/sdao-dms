import { Badge } from '@/components/ui/badge';
import { cn, statusLabel } from '@/lib/utils';

const statusStyles: Record<string, string> = {
    draft: 'bg-muted text-muted-foreground border-border',
    in_review: 'bg-info/10 text-info-foreground border-info/20',
    returned: 'bg-warning/10 text-warning-foreground border-warning/20',
    approved: 'bg-success/10 text-success-foreground border-success/20',
    rejected: 'bg-destructive/10 text-destructive-foreground border-destructive/20',
};

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
