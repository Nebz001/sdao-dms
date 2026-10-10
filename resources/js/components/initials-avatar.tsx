import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { BRAND_TINT } from '@/lib/brand-tint';
import { cn } from '@/lib/utils';

/** A person's initials in the one NU blue tint (lib/brand-tint.ts). */
export default function InitialsAvatar({
    name,
    className,
    fallbackClassName,
}: {
    name: string;
    /** Size and shape of the circle, e.g. "size-9". Defaults to size-10. */
    className?: string;
    fallbackClassName?: string;
}) {
    const getInitials = useInitials();

    return (
        <Avatar className={cn('size-10', className)}>
            <AvatarFallback className={cn('text-xs font-semibold', BRAND_TINT, fallbackClassName)}>
                {getInitials(name)}
            </AvatarFallback>
        </Avatar>
    );
}
