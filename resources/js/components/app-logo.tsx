import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            {/* Both `!` are load-bearing, not decorative. SidebarMenuButton's
                base classes include `[&>svg]:size-4` — a direct-child-of-svg
                selector, which is a compound selector (class + type) and so
                has strictly higher CSS specificity than a plain `.size-12`
                class on the svg itself. Without `!important` that rule always
                wins regardless of source order, silently forcing the seal
                back down to 16px. Same reasoning applies to the collapsed
                override below: the button force-caps to a 32px square there
                (`group-data-[collapsible=icon]:size-8!`), and without a
                matching `!` override the seal would overflow that box and
                get clipped by its `overflow-hidden`. */}
            <AppLogoIcon className="size-12! shrink-0 group-data-[collapsible=icon]:size-8!" />
            <div className="ml-2 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {import.meta.env.VITE_APP_NAME || 'SDAO-DMS'}
                </span>
            </div>
        </>
    );
}
