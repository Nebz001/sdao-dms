import { Users } from 'lucide-react';
import TagBadge from '@/components/tag-badge';
import { cn } from '@/lib/utils';

type OrganizationBadgeProps = {
    name: string;
    className?: string;
};

/**
 * An organization's name as a badge: a users icon and an outlined, foreground
 * colored pill. Its sibling, FormTypeBadge, is brand-tinted with a file icon,
 * so the two read as different things when they sit side by side. Use both
 * wherever a document's organization and form type are shown together.
 */
export default function OrganizationBadge({ name, className }: OrganizationBadgeProps) {
    return (
        <TagBadge
            title={name}
            className={cn('border-border bg-background font-medium text-foreground', className)}
        >
            <Users aria-hidden />
            {name}
        </TagBadge>
    );
}
