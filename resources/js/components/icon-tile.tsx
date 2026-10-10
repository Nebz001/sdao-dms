import type { LucideIcon } from 'lucide-react';
import { BRAND_TINT } from '@/lib/brand-tint';
import { cn } from '@/lib/utils';

const SIZES = {
    sm: { box: 'size-10 rounded-lg', icon: 'size-5' },
    md: { box: 'size-12 rounded-lg', icon: 'size-6' },
    lg: { box: 'size-14 rounded-xl', icon: 'size-7' },
} as const;

/**
 * The tinted icon square used on the home cards, hub options, headers and list
 * rows. Always the one NU blue tint (see lib/brand-tint.ts): the icon says what
 * it is, color is kept for status.
 */
export default function IconTile({
    icon: Icon,
    size = 'md',
    className,
}: {
    icon: LucideIcon;
    size?: keyof typeof SIZES;
    className?: string;
}) {
    return (
        <span
            aria-hidden
            className={cn('flex shrink-0 items-center justify-center', SIZES[size].box, BRAND_TINT, className)}
        >
            <Icon className={SIZES[size].icon} />
        </span>
    );
}
