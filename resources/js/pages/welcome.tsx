import { Head, Link } from '@inertiajs/react';
import {
    Bell,
    ClipboardCheck,
    FileCheck2,
    Moon,
    SendHorizonal,
    Sun,
    UserPlus,
    Users,
} from 'lucide-react';
import AppLogoLockup from '@/components/app-logo-lockup';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useAppearance } from '@/hooks/use-appearance';
import { home, login, register } from '@/routes';

const audiences = [
    {
        icon: Users,
        title: 'Student Organizations',
        description:
            'Register your organization, submit activity proposals, calendars, and after-activity reports, and track each document’s approval status in real time.',
    },
    {
        icon: ClipboardCheck,
        title: 'Faculty & Staff Approvers',
        description:
            'Review submissions routed to your role, approve or return them for revision, and keep every organization’s paperwork moving without manual follow-ups.',
    },
];

type StepAccent = 'primary' | 'info' | 'success';

const stepAccents: Record<
    StepAccent,
    { ring: string; icon: string; bar: string }
> = {
    primary: {
        ring: 'border-primary bg-primary/10',
        icon: 'text-primary',
        bar: 'bg-primary/40',
    },
    info: {
        ring: 'border-info bg-info/10',
        icon: 'text-info',
        bar: 'bg-info/40',
    },
    success: {
        ring: 'border-success bg-success/10',
        icon: 'text-success',
        bar: 'bg-success/40',
    },
};

const steps: { icon: typeof UserPlus; label: string; accent: StepAccent }[] = [
    { icon: UserPlus, label: 'Register your organization', accent: 'primary' },
    { icon: SendHorizonal, label: 'Submit forms digitally', accent: 'primary' },
    {
        icon: FileCheck2,
        label: 'Track approval status in real time',
        accent: 'info',
    },
    { icon: Bell, label: 'Get notified at every step', accent: 'success' },
];

export default function Welcome() {
    const { resolvedAppearance, updateAppearance } = useAppearance();

    return (
        <>
            <Head title="Welcome" />

            <div className="flex min-h-svh flex-col bg-background">
                <header className="border-b">
                    <div className="mx-auto flex h-16 w-full max-w-5xl items-center justify-between px-6 sm:h-20">
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

                        <div className="absolute inset-x-0 bottom-0 mx-auto w-full max-w-5xl px-6 pb-10 sm:pb-14 lg:pb-16">
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

                    {/* Who this is for */}
                    <section className="border-t bg-muted/30">
                        <div className="mx-auto w-full max-w-5xl px-6 py-14">
                            <h2 className="text-sm font-medium text-muted-foreground">
                                Who this is for
                            </h2>
                            <div className="mt-6 grid gap-4 md:grid-cols-2">
                                {audiences.map(
                                    ({ icon: Icon, title, description }) => (
                                        <Card
                                            key={title}
                                            className="border-l-4 border-l-primary transition-shadow hover:shadow-md"
                                        >
                                            <CardHeader>
                                                <div className="flex size-10 items-center justify-center rounded-lg bg-muted text-foreground">
                                                    <Icon
                                                        className="size-5"
                                                        aria-hidden="true"
                                                    />
                                                </div>
                                                <CardTitle className="mt-2">
                                                    {title}
                                                </CardTitle>
                                                <CardDescription>
                                                    {description}
                                                </CardDescription>
                                            </CardHeader>
                                        </Card>
                                    ),
                                )}
                            </div>
                            <p className="mt-4 text-sm text-muted-foreground">
                                Both sign in from the same place &mdash; your
                                access is set by your role.
                            </p>
                        </div>
                    </section>

                    {/* How it works */}
                    <section className="mx-auto w-full max-w-5xl px-6 py-14">
                        <h2 className="text-sm font-medium text-muted-foreground">
                            How it works
                        </h2>
                        <ol className="mt-6 grid gap-6 sm:grid-cols-2 md:grid-cols-4">
                            {steps.map(
                                ({ icon: Icon, label, accent }, index) => {
                                    const nextAccent = steps[index + 1]?.accent;

                                    return (
                                        <li
                                            key={label}
                                            className="relative flex flex-col gap-3"
                                        >
                                            <div className="flex items-center gap-3">
                                                <div
                                                    className={`flex size-11 shrink-0 items-center justify-center rounded-full border-2 ${stepAccents[accent].ring}`}
                                                >
                                                    <Icon
                                                        className={`size-5 ${stepAccents[accent].icon}`}
                                                        aria-hidden="true"
                                                    />
                                                </div>
                                                {nextAccent && (
                                                    <div
                                                        aria-hidden
                                                        className={`hidden h-0.5 flex-1 rounded-full sm:block md:hidden lg:block ${stepAccents[nextAccent].bar}`}
                                                    />
                                                )}
                                            </div>
                                            <p className="text-sm font-medium">
                                                {label}
                                            </p>
                                        </li>
                                    );
                                },
                            )}
                        </ol>
                    </section>
                </main>

                <footer className="border-t">
                    <div className="mx-auto w-full max-w-5xl px-6 py-6 text-sm text-muted-foreground">
                        NU Lipa &middot; Student Development and Activities
                        Office
                    </div>
                </footer>
            </div>
        </>
    );
}
