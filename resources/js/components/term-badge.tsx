import { CalendarDays } from 'lucide-react';
import TagBadge from '@/components/tag-badge';
import { cn } from '@/lib/utils';

type TermBadgeProps = {
    /** "2nd Term, 2026-2027", as the server words it (AcademicPeriod::label()). */
    label: string;
    className?: string;
};

/**
 * The term a renewal was filed for, beside the organization and form type
 * badges. Outlined like OrganizationBadge but muted, with a calendar icon, so
 * the three read as three different things in one row: who (users icon),
 * what (file icon, brand tint) and when (calendar icon).
 */
export default function TermBadge({ label, className }: TermBadgeProps) {
    return (
        <TagBadge
            title={label}
            className={cn('border-border bg-background font-medium whitespace-nowrap text-muted-foreground', className)}
        >
            <CalendarDays aria-hidden />
            {label}
        </TagBadge>
    );
}
