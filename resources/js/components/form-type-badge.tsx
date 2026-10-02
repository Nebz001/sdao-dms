import TagBadge from '@/components/tag-badge';

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
export default function FormTypeBadge({ label, className }: FormTypeBadgeProps) {
    return <TagBadge className={className}>{label}</TagBadge>;
}
