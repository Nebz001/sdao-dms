import { FileText } from 'lucide-react';
import { ToneBadge } from '@/components/status-badge';
import TagBadge from '@/components/tag-badge';
import { BRAND_TINT_OUTLINE } from '@/lib/brand-tint';
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
            className={cn(BRAND_TINT_OUTLINE, className)}
        >
            <FileText aria-hidden />
            {label}
        </ToneBadge>
    );
}

/**
 * The form type beside a document's title: a file icon on the brand tint.
 * Pairs with OrganizationBadge (users icon, outlined) so the two differ in
 * both icon and style.
 */
export default function FormTypeBadge({ label, className }: FormTypeBadgeProps) {
    return (
        <TagBadge className={cn(BRAND_TINT_OUTLINE, 'font-medium', className)}>
            <FileText aria-hidden />
            {label}
        </TagBadge>
    );
}
