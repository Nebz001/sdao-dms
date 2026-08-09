import { cn } from '@/lib/utils';

/**
 * The NU Lipa lockup for surfaces whose background is the INVERSE of the
 * page's own theme — specifically the footer, which sits on `bg-brand`
 * (navy in light theme, gold in dark theme: the opposite of the page's own
 * light/dark surface at that moment).
 *
 * AppLogoLockup's normal `dark:` swap assumes the surface matches the
 * page theme, so it picks the wrong file here: the light-theme footer
 * (navy bg) would get the navy-wordmark logo (navy-on-navy, 1.01:1), and
 * the dark-theme footer (gold bg) would get the white-wordmark logo
 * (white-on-gold, 1.53:1) — both illegible. This component swaps the same
 * two SVGs the other way — white-wordmark in light theme (on navy),
 * navy-wordmark in dark theme (on gold) — still pure CSS via `dark:`, so
 * it's still correct on the very first paint with no JS-driven flash (see
 * AppLogoLockup's own docblock for why that matters). Not `fetchPriority`
 * "high" — unlike the navbar's instance, this one is below the fold.
 */
export default function AppLogoLockupInverted({
    className,
}: {
    className?: string;
}) {
    return (
        <>
            <img
                src="/images/logo/nulp-logo-dark-bg.svg"
                alt="NU Lipa"
                width={6250}
                height={4434}
                decoding="async"
                className={cn('w-auto dark:hidden', className)}
            />
            <img
                src="/images/logo/nulp-logo-light-bg.svg"
                alt="NU Lipa"
                width={6250}
                height={4434}
                decoding="async"
                className={cn('hidden w-auto dark:block', className)}
            />
        </>
    );
}
