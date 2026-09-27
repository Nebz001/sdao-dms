import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type FormTypeBadgeProps = {
    label: string;
    className?: string;
};

/**
 * A document's form type (Registration, Renewal, Activity Calendar,
 * Activity Proposal, After-Activity Report) — unlike StatusBadge/
 * ActionBadge, form type carries no shared meaning across the app (no
 * "green means X" convention), so it stays a neutral secondary chip rather
 * than borrowing a color from the success/warning/destructive/info family.
 */
export default function FormTypeBadge({
    label,
    className,
}: FormTypeBadgeProps) {
    return (
        <Badge variant="secondary" className={cn('font-medium', className)}>
            {label}
        </Badge>
    );
}
