import { cn } from '@/lib/utils';

/**
 * The full NU Lipa brand lockup — shield + "NU LIPA" wordmark in one SVG —
 * used wherever there's room for more than the bare seal (`AppLogoIcon`):
 * the landing page navbar today, the footer next. Renders both theme
 * variants and lets CSS pick one via `dark:`, rather than switching the
 * `src` in JS off `useAppearance().resolvedAppearance`. That hook's
 * `useSyncExternalStore` server snapshot is always `'system'`, and without
 * a `window` to query `prefers-color-scheme`, it resolves to `'light'` on
 * every hydration — a JS-driven swap would flash the light-theme (navy
 * wordmark) logo for dark-theme users on every load. CSS keys off the
 * `dark` class instead, which `app.blade.php`'s inline script already sets
 * on `<html>` before first paint, so the correct variant is correct on the
 * very first frame.
 *
 * `className` sizes the lockup (e.g. `h-10`) and is applied to both
 * `<img>`s; callers control height only — width follows from the SVGs'
 * shared ~1.41:1 (6250×4434) aspect ratio.
 */
export default function AppLogoLockup({
    className,
}: {
    className?: string;
}) {
    return (
        <>
            <img
                src="/images/logo/nulp-logo-light-bg.svg"
                alt="NU Lipa"
                width={6250}
                height={4434}
                fetchPriority="high"
                decoding="async"
                className={cn('w-auto dark:hidden', className)}
            />
            <img
                src="/images/logo/nulp-logo-dark-bg.svg"
                alt="NU Lipa"
                width={6250}
                height={4434}
                fetchPriority="high"
                decoding="async"
                className={cn('hidden w-auto dark:block', className)}
            />
        </>
    );
}
