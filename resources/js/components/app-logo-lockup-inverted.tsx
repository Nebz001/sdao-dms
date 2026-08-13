import { cn } from '@/lib/utils';

/**
 * The NU Lipa lockup for the landing page footer, which sits on
 * `bg-brand-fixed` — always navy, regardless of the page's own light/dark
 * theme. Unlike `AppLogoLockup` (which swaps wordmark color via `dark:` to
 * track the page theme), this footer's background never changes, so it
 * always needs the white-wordmark file — no theme-conditional swap.
 */
export default function AppLogoLockupInverted({
    className,
}: {
    className?: string;
}) {
    return (
        <img
            src="/images/logo/nulp-logo-dark-bg.svg"
            alt="NU Lipa"
            width={6250}
            height={4434}
            decoding="async"
            className={cn('w-auto', className)}
        />
    );
}
