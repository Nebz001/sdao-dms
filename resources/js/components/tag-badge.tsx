import type { ComponentProps } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type TagBadgeProps = Omit<ComponentProps<typeof Badge>, 'variant'>;

/**
 * The neutral tag for names and labels that carry no status: an organization
 * or school, an approver's role and scope, a form type, a passkey's
 * authenticator. Soft gray fill, normal case, rounded, no strong border.
 *
 * Long names wrap instead of truncating (so nothing is hidden at tablet
 * widths), which is why the badge's fixed height and no-wrap defaults are
 * released here.
 */
export default function TagBadge({ className, ...props }: TagBadgeProps) {
    return (
        <Badge
            variant="secondary"
            className={cn(
                'h-auto max-w-full rounded-md text-left font-normal break-words whitespace-normal',
                className,
            )}
            {...props}
        />
    );
}
