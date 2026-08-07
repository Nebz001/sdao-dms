import { Badge } from '@/components/ui/badge';
import { cn, statusLabel } from '@/lib/utils';

/**
 * Solid fill chips, not tinted — status is the one thing an approver scans
 * for, so it gets the loudest treatment on the page. `border-transparent` is
 * required, not decorative: app.css's `* { @apply border-border }` base rule
 * would otherwise paint a zinc outline around every chip. `text-background`
 * resolves to the correct on-fill color in both themes (near-white on the
 * 600-level light fills, near-black on the 500-level dark fills) without a
 * manual `dark:` branch. Draft stays a neutral `bg-muted` chip — the
 * "nothing happening yet" state shouldn't compete with the four active ones.
 */
const statusStyles: Record<string, string> = {
    draft: 'border-transparent bg-muted text-muted-foreground',
    in_review: 'border-transparent bg-info text-background',
    returned: 'border-transparent bg-warning text-background',
    approved: 'border-transparent bg-success text-background',
    rejected: 'border-transparent bg-destructive text-white',
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
 * Shared solid-chip status badge used across every document list/show and
 * review page instead of a per-page `statusVariant` map. Pill-shaped
 * (`rounded-full`) here only — every other badge in the app keeps the base
 * `ui/badge.tsx` `rounded-md`, so shape and color both carry the status
 * distinction from the app's neutral count/role badges.
 */
export function StatusBadge({ status, className }: StatusBadgeProps) {
    return (
        <Badge
            variant="outline"
            className={cn(
                'rounded-full font-semibold',
                statusStyles[status] ?? statusStyles.draft,
                className,
            )}
        >
            {statusLabel(status)}
        </Badge>
    );
}
