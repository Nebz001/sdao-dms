import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Card, CardContent } from '@/components/ui/card';
import { Toaster } from '@/components/ui/sonner';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/**
 * Shared layout for every `auth/*` page (see the `layout:` resolver in
 * app.tsx — login, register, forgot/reset password, 2FA challenge, email
 * verification all render through this one template). The photo background
 * and scrim are the exact same asset + gradient markup as the landing
 * page's hero (resources/js/pages/welcome.tsx) — reused, not recreated.
 *
 * Unlike the hero, nothing here sits directly on the scrim: the logo,
 * heading, description, and every form field live inside an opaque `Card`,
 * so none of it needs the hero's white/gold on-photo text treatment. The
 * card's own colors (`bg-card`, normal foreground) are unaffected by
 * whatever photo is behind it — only the page background changed, which is
 * why the card gets a stronger shadow (`shadow-2xl` vs the default
 * `shadow-sm`) to visibly lift off a now-busier backdrop.
 */
export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center gap-6 overflow-hidden bg-background p-6 md:p-10">
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
            <div
                aria-hidden="true"
                className="absolute inset-0 bg-linear-to-t from-black/80 from-0% via-black/80 via-62% to-transparent to-85% dark:from-black/90 dark:from-0% dark:via-black/90 dark:via-62% dark:to-transparent dark:to-85%"
            />
            <div
                aria-hidden="true"
                className="absolute inset-0 bg-linear-to-tr from-brand/55 from-0% via-brand/55 via-58% to-transparent to-80% dark:from-brand/22 dark:from-0% dark:via-brand/22 dark:via-55% dark:to-transparent dark:to-78%"
            />

            <Toaster />
            <Card className="relative w-full max-w-sm shadow-2xl">
                <CardContent className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={home()}
                            className="flex flex-col items-center gap-2 font-medium"
                        >
                            <div className="mb-1 flex h-9 w-9 items-center justify-center rounded-md">
                                <AppLogoIcon className="size-9" />
                            </div>
                            <span className="sr-only">{title}</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-medium">{title}</h1>
                            <p className="text-center text-sm text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </CardContent>
            </Card>
        </div>
    );
}
