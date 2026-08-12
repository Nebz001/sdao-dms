import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            {/* Both `!` are load-bearing, not decorative. SidebarMenuButton's
                base classes include `[&>svg]:size-4` — a direct-child-of-svg
                selector, which is a compound selector (class + type) and so
                has strictly higher CSS specificity than a plain `.size-10`
                class on the svg itself. Without `!important` that rule always
                wins regardless of source order, silently forcing the seal
                back down to 16px. Same reasoning applies to the collapsed
                override below: the button force-caps to a 32px square there
                (`group-data-[collapsible=icon]:size-8!`), and without a
                matching `!` override the seal would overflow that box and
                get clipped by its `overflow-hidden`. */}
            <AppLogoIcon className="size-10! shrink-0 group-data-[collapsible=icon]:size-8!" />
            {/* Two-line title block, the same "org name, then a smaller
                muted subtitle" pattern shadcn's own sidebar examples use for
                a team/workspace switcher. The combined text is exactly the
                landing hero's eyebrow ("SDAO Paperless Documentation
                System"), split across two lines instead of one. */}
            <div className="ml-2 grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-bold">SDAO</span>
                {/* No `truncate` here on purpose — at the sidebar's fixed
                    width this phrase doesn't fit on one line even at
                    text-xs, and clipping it was the bug being fixed. Letting
                    it wrap keeps the full subtitle legible. */}
                <span className="text-xs text-muted-foreground">
                    Paperless Documentation System
                </span>
            </div>
        </>
    );
}
