import { Head, Link } from '@inertiajs/react';
import { Mail, MapPin, Moon, Phone, Sun } from 'lucide-react';
import AppLogoLockup from '@/components/app-logo-lockup';
import AppLogoLockupInverted from '@/components/app-logo-lockup-inverted';
import PublicActivitiesSection from '@/components/public-activities-section';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { home, login, register } from '@/routes';
import type { PublicActivity } from '@/types/public-activity';

type Props = {
    upcomingActivities: PublicActivity[];
};

export default function Welcome({ upcomingActivities }: Props) {
    const { resolvedAppearance, updateAppearance } = useAppearance();

    return (
        <>
            <Head title="Welcome" />

            <div className="flex min-h-svh flex-col bg-background">
                <header className="border-b">
                    <div className="mx-auto flex h-16 w-full max-w-7xl items-center justify-between px-6 sm:h-20">
                        <Link
                            href={home()}
                            className="inline-flex items-center rounded-md outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <AppLogoLockup className="h-10" />
                        </Link>
                        <div className="flex items-center gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={
                                    resolvedAppearance === 'dark'
                                        ? 'Switch to light mode'
                                        : 'Switch to dark mode'
                                }
                                title={
                                    resolvedAppearance === 'dark'
                                        ? 'Switch to light mode'
                                        : 'Switch to dark mode'
                                }
                                onClick={() =>
                                    updateAppearance(
                                        resolvedAppearance === 'dark'
                                            ? 'light'
                                            : 'dark',
                                    )
                                }
                            >
                                {resolvedAppearance === 'dark' ? (
                                    <Sun />
                                ) : (
                                    <Moon />
                                )}
                                <span className="sr-only">Toggle theme</span>
                            </Button>
                            <Button variant="brand" size="sm" asChild>
                                <Link href={login()}>Log in</Link>
                            </Button>
                        </div>
                    </div>
                </header>

                <main className="flex-1">
                    {/* Hero */}
                    {/* Height is deliberately NOT a simple ascending scale.
                        Below `sm` the box is narrower than it is tall
                        relative to the photo's ~3:2 ratio, so `object-fit:
                        cover` matches on HEIGHT and shows the photo's full
                        height uncropped — meaning `object-position`'s Y
                        component has zero effect there and the shield sits
                        at a fixed ~22-32% down the section, wherever that
                        lands. Mobile text also wraps onto more lines (the
                        eyebrow alone goes from 1 line to 2 under ~400px).
                        So mobile needs the MOST vertical room, not the
                        least, to keep the bottom-anchored copy block clear
                        of that fixed shield band — hence a taller base
                        height than `sm`. */}
                    <section className="relative h-[38rem] overflow-hidden sm:h-[34rem] lg:h-[38rem]">
                        <img
                            src="/images/nulp-building-1600.webp"
                            srcSet="/images/nulp-building-960.webp 960w, /images/nulp-building-1600.webp 1600w, /images/nulp-building-2400.webp 2400w"
                            sizes="100vw"
                            width={4800}
                            height={3201}
                            alt=""
                            fetchPriority="high"
                            decoding="async"
                            className="absolute inset-0 size-full object-cover object-[30%_26%]"
                        />
                        {/* Scrim, two stacked layers: a neutral darkener that
                            does the contrast work, plus a brand-hue layer on
                            top of it carrying --brand. Both are bottom-heavy
                            and fade out toward the upper-right, so the copy
                            block (bottom-left) sits on a strong scrim while
                            the shield on the building facade (upper-left)
                            stays clear of it. Split into two layers because
                            the two themes need very different brand
                            opacities: navy can carry real weight itself,
                            gold cannot (gold-on-white-text fails contrast on
                            its own), so gold stays a thin hue glaze over a
                            heavier black base while navy leans on its own
                            darkness more and needs less black underneath. */}
                        <div
                            aria-hidden="true"
                            className="absolute inset-0 bg-linear-to-t from-black/80 from-0% via-black/80 via-62% to-transparent to-85% dark:from-black/90 dark:from-0% dark:via-black/90 dark:via-62% dark:to-transparent dark:to-85%"
                        />
                        <div
                            aria-hidden="true"
                            className="absolute inset-0 bg-linear-to-tr from-brand/55 from-0% via-brand/55 via-58% to-transparent to-80% dark:from-brand/22 dark:from-0% dark:via-brand/22 dark:via-55% dark:to-transparent dark:to-78%"
                        />

                        <div className="absolute inset-x-0 bottom-0 mx-auto w-full max-w-7xl px-6 pb-10 sm:pb-14 lg:pb-16">
                            <p className="text-xs font-semibold tracking-[0.2em] text-brand-accent uppercase motion-safe:animate-[hero-rise_0.5s_ease-out_forwards]">
                                SDAO Paperless Documentation System
                            </p>
                            <h1 className="mt-3 max-w-2xl text-4xl font-semibold tracking-tight text-balance text-white motion-safe:animate-[hero-rise_0.5s_ease-out_80ms_forwards] sm:text-5xl lg:text-6xl">
                                File it once.
                                <br />
                                Track it to approval.
                            </h1>
                            <p className="mt-4 max-w-xl text-base text-white/80 motion-safe:animate-[hero-rise_0.5s_ease-out_160ms_forwards] sm:text-lg">
                                Student organizations submit registrations,
                                proposals, calendars, and reports online. Each
                                one routes to the right approvers
                                automatically, and every status change shows
                                up live.
                            </p>
                            <div className="mt-8 flex flex-wrap gap-3 motion-safe:animate-[hero-rise_0.5s_ease-out_240ms_forwards]">
                                <Button size="lg" variant="brand" asChild>
                                    <Link href={login()}>Log in</Link>
                                </Button>
                                <Button
                                    size="lg"
                                    variant="outline"
                                    className="border-white/80 bg-black/25 text-white backdrop-blur-sm hover:bg-black/35 hover:text-white"
                                    asChild
                                >
                                    <Link href={register()}>
                                        Create account
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </section>

                    <PublicActivitiesSection
                        activities={upcomingActivities}
                    />
                </main>

                {/* Footer sits on `bg-brand` — deliberately the INVERSE of
                    the page's own theme (navy in light theme, gold in dark
                    theme). Every color in here is `text-brand-foreground`
                    based, not `text-muted-foreground`/`text-foreground` —
                    those page-body tokens track the page's own light/dark
                    surface, and would silently fail contrast against this
                    surface's opposite background (measured: navy body text
                    on this navy footer, or light text on this gold footer,
                    both under 1.6:1). `--brand-foreground` already flips
                    white/near-black exactly opposite to `--brand` itself,
                    which is what makes it the correct pairing here — same
                    reasoning as the logo swap in AppLogoLockupInverted. */}
                <footer className="bg-brand">
                    <div className="mx-auto w-full max-w-7xl px-6 py-12">
                        <div className="grid grid-cols-1 gap-10 sm:grid-cols-3">
                            <div>
                                <AppLogoLockupInverted className="h-10" />
                                <p className="mt-3 text-sm text-brand-foreground/80">
                                    Student Development and Activities
                                    Office
                                </p>
                            </div>

                            <div>
                                <p className="text-xs font-bold tracking-wide text-brand-foreground/80 uppercase">
                                    About
                                </p>
                                <p className="mt-3 text-sm text-brand-foreground/80">
                                    A paperless way for NU Lipa student
                                    organizations to submit, route, and
                                    track approvals &mdash; all in one
                                    place.
                                </p>
                            </div>

                            <div>
                                <p className="text-xs font-bold tracking-wide text-brand-foreground/80 uppercase">
                                    Contact
                                </p>
                                <ul className="mt-3 flex flex-col gap-2">
                                    <li>
                                        <a
                                            href="mailto:sdao@nu-lipa.edu.ph"
                                            className="flex items-center gap-2 text-sm text-brand-foreground/80 hover:text-brand-foreground hover:underline underline-offset-2"
                                        >
                                            <Mail
                                                className="size-4 shrink-0"
                                                aria-hidden="true"
                                            />
                                            sdao@nu-lipa.edu.ph
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="tel:+63431234567"
                                            className="flex items-center gap-2 text-sm text-brand-foreground/80 hover:text-brand-foreground hover:underline underline-offset-2"
                                        >
                                            <Phone
                                                className="size-4 shrink-0"
                                                aria-hidden="true"
                                            />
                                            (043) 123 4567
                                        </a>
                                    </li>
                                    <li className="flex items-center gap-2 text-sm text-brand-foreground/80">
                                        <MapPin
                                            className="size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                        SDAO Office, NU Lipa
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div className="mt-10 border-t border-brand-foreground/20 pt-6">
                            <p className="text-center text-xs text-brand-foreground/70">
                                &copy; {new Date().getFullYear()} NU Lipa
                                &middot; SDAO Paperless Documentation
                                System. All rights reserved.
                            </p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
