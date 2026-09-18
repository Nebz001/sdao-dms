import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import type { AuthOrganization } from '@/types/auth';

/**
 * Top-branding swap-in for a president/secretary — see app-sidebar.tsx,
 * which renders this instead of <AppLogo /> whenever auth.organization is
 * non-null. Takes the org as a prop (no usePage() of its own) so it's
 * directly unit-testable.
 *
 * Unlike AppLogo, no `!important` sizing overrides are needed:
 * SidebarMenuButton's `[&>svg]:size-4` selector (see components/ui/sidebar.tsx)
 * only matches a direct svg child, and the mark here is an <Avatar> (a span)
 * with the image/fallback nested inside it, so that rule never applies.
 */
export default function OrgBranding({ organization }: { organization: AuthOrganization }) {
    return (
        <>
            <Avatar className="size-10 shrink-0 rounded-md group-data-[collapsible=icon]:size-8">
                <AvatarImage src={organization.logoUrl ?? undefined} alt={organization.name} />
                <AvatarFallback className="rounded-md bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                    {orgMonogram(organization.name)}
                </AvatarFallback>
            </Avatar>
            <div className="ml-2 grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-bold" title={organization.name}>
                    {organization.name}
                </span>
            </div>
        </>
    );
}

/**
 * Deliberately NOT the shared useInitials() hook. That hook returns a
 * single letter for a one-word name — correct for a person ("Madonna" →
 * "M"), but most org names are already acronyms (CODECS, COMEX, MALAYA,
 * NEXUS, TAG, PHISMET, APPLIQUE), so the same rule collapses them to one
 * meaningless letter ("C"). Here a one-word name instead takes its first
 * two letters; a multi-word name still takes first+last initials, same as
 * useInitials(). Kept local to this component rather than folded into the
 * shared hook, since useInitials() is applied to person names everywhere
 * else it's used (user-info.tsx, admin/dashboard.tsx) and should keep its
 * current one-word behavior there.
 */
function orgMonogram(name: string): string {
    const words = name.trim().split(' ').filter(Boolean);

    if (words.length === 0) {
        return '';
    }

    if (words.length === 1) {
        return words[0].slice(0, 2).toUpperCase();
    }

    return `${words[0].charAt(0)}${words[words.length - 1].charAt(0)}`.toUpperCase();
}
