import { ToneBadge } from '@/components/status-badge';
import TagBadge from '@/components/tag-badge';
import { cn } from '@/lib/utils';

type FormTypeBadgeProps = {
    label: string;
    className?: string;
};

/**
 * A document's form type (Registration, Renewal, Activity Calendar, Activity
 * Proposal, After-Activity Report). Form type carries no shared meaning across
 * the app (no "green means X" convention), so it is a neutral tag rather than
 * a tone.
 */
/**
 * The form type as the app-wide outlined, tinted, uppercase badge, in the
 * brand (primary) tint so it never reads as one of the status colors beside
 * an Approved or Rejected badge. Used above a document title in list tables.
 */
export function FormTypeLabelBadge({ label, className }: FormTypeBadgeProps) {
    return (
        <ToneBadge
            tone="neutral"
            className={cn('border-primary/40 bg-primary/10 text-primary-text', className)}
        >
            {label}
        </ToneBadge>
    );
}

export default function FormTypeBadge({ label, className }: FormTypeBadgeProps) {
    return <TagBadge className={className}>{label}</TagBadge>;
}
