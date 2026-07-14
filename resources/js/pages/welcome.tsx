import { Head, Link } from '@inertiajs/react';
import { Bell, ClipboardCheck, FileCheck2, SendHorizonal, UserPlus, Users } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { login, register } from '@/routes';

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

const steps = [
    { icon: UserPlus, label: 'Register your organization' },
    { icon: SendHorizonal, label: 'Submit forms digitally' },
    { icon: FileCheck2, label: 'Track approval status in real time' },
    { icon: Bell, label: 'Get notified at every step' },
];

export default function Welcome() {
    return (
        <>
            <Head title="Welcome" />

            <div className="flex min-h-svh flex-col bg-background">
                <header className="border-b">
                    <div className="mx-auto flex w-full max-w-5xl items-center justify-between px-6 py-4">
                        <div className="flex items-center gap-2">
                            <AppLogoIcon className="size-6 fill-current text-foreground" />
                            <span className="text-sm font-semibold tracking-tight">
                                {import.meta.env.VITE_APP_NAME || 'SDAO-DMS'}
                            </span>
                        </div>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={login()}>Log in</Link>
                        </Button>
                    </div>
                </header>

                <main className="flex-1">
                    {/* Hero */}
                    <section className="mx-auto w-full max-w-5xl px-6 py-16 sm:py-24">
                        <h1 className="max-w-3xl text-3xl font-semibold tracking-tight sm:text-4xl">
                            SDAO Paperless Documentation System
                        </h1>
                        <p className="mt-4 max-w-2xl text-base text-muted-foreground sm:text-lg">
                            An online system for NU Lipa student organizations to submit registrations,
                            activity proposals, calendars, and reports &mdash; and for approvers to review
                            and approve them digitally, replacing manual paper routing.
                        </p>
                        <div className="mt-8 flex flex-wrap gap-3">
                            <Button size="lg" asChild>
                                <Link href={login()}>Log in</Link>
                            </Button>
                            <Button size="lg" variant="outline" asChild>
                                <Link href={register()}>Create account</Link>
                            </Button>
                        </div>
                    </section>

                    {/* Who this is for */}
                    <section className="border-t bg-muted/30">
                        <div className="mx-auto w-full max-w-5xl px-6 py-14">
                            <h2 className="text-sm font-medium text-muted-foreground">Who this is for</h2>
                            <div className="mt-6 grid gap-4 md:grid-cols-2">
                                {audiences.map(({ icon: Icon, title, description }) => (
                                    <Card key={title}>
                                        <CardHeader>
                                            <Icon className="size-5 text-muted-foreground" />
                                            <CardTitle className="mt-2">{title}</CardTitle>
                                            <CardDescription>{description}</CardDescription>
                                        </CardHeader>
                                    </Card>
                                ))}
                            </div>
                            <p className="mt-4 text-sm text-muted-foreground">
                                Both sign in from the same place &mdash; your access is set by your role.
                            </p>
                        </div>
                    </section>

                    {/* How it works */}
                    <section className="mx-auto w-full max-w-5xl px-6 py-14">
                        <h2 className="text-sm font-medium text-muted-foreground">How it works</h2>
                        <ol className="mt-6 grid gap-6 sm:grid-cols-2 md:grid-cols-4">
                            {steps.map(({ icon: Icon, label }, index) => (
                                <li key={label} className="relative flex flex-col gap-3">
                                    <div className="flex items-center gap-3">
                                        <div className="flex size-9 shrink-0 items-center justify-center rounded-full border bg-card">
                                            <Icon className="size-4 text-foreground" />
                                        </div>
                                        {index < steps.length - 1 && (
                                            <div
                                                aria-hidden
                                                className="hidden h-px flex-1 bg-border sm:block md:hidden lg:block"
                                            />
                                        )}
                                    </div>
                                    <p className="text-sm font-medium">{label}</p>
                                </li>
                            ))}
                        </ol>
                    </section>
                </main>

                <footer className="border-t">
                    <div className="mx-auto w-full max-w-5xl px-6 py-6 text-sm text-muted-foreground">
                        NU Lipa &middot; Student Development and Activities Office
                    </div>
                </footer>
            </div>
        </>
    );
}
