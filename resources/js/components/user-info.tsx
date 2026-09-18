import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import type { User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
    subtitle,
}: {
    user: User;
    showEmail?: boolean;
    /**
     * Separate from showEmail on purpose: nav-user.tsx (the sidebar footer)
     * passes this with a president/secretary's org school; the dropdown's
     * UserMenuContent only ever passes showEmail. Keeping them distinct
     * props means neither call site's rendering can affect the other's.
     */
    subtitle?: string;
}) {
    const getInitials = useInitials();

    return (
        <>
            <Avatar className="h-8 w-8 overflow-hidden rounded-full">
                <AvatarImage src={user.avatar} alt={user.name} />
                <AvatarFallback className="rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                    {getInitials(user.name)}
                </AvatarFallback>
            </Avatar>
            <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{user.name}</span>
                {showEmail && (
                    <span className="truncate text-xs text-muted-foreground">
                        {user.email}
                    </span>
                )}
                {subtitle && (
                    <span
                        className="truncate text-xs text-muted-foreground"
                        title={subtitle}
                    >
                        {subtitle}
                    </span>
                )}
            </div>
        </>
    );
}
