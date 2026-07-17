import { Head, Link } from '@inertiajs/react';
import { Bell, ClipboardCheck, FileCheck2, SendHorizonal, UserPlus, Users } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import LandingFlowDiagram from '@/components/landing-flow-diagram';
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

type StepAccent = 'primary' | 'info' | 'success';

const stepAccents: Record<StepAccent, { ring: string; icon: string; bar: string }> = {
    primary: { ring: 'border-primary bg-primary/10', icon: 'text-primary', bar: 'bg-primary/40' },
    info: { ring: 'border-info bg-info/10', icon: 'text-info', bar: 'bg-info/40' },
    success: { ring: 'border-success bg-success/10', icon: 'text-success', bar: 'bg-success/40' },
};

const steps: { icon: typeof UserPlus; label: string; accent: StepAccent }[] = [
    { icon: UserPlus, label: 'Register your organization', accent: 'primary' },
    { icon: SendHorizonal, label: 'Submit forms digitally', accent: 'primary' },
    { icon: FileCheck2, label: 'Track approval status in real time', accent: 'info' },
    { icon: Bell, label: 'Get notified at every step', accent: 'success' },
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
                    <section className="relative overflow-hidden">
                        <LandingFlowDiagram className="pointer-events-none absolute inset-y-0 right-[-6rem] hidden h-full w-[48rem] md:block" />

                        <div className="relative mx-auto w-full max-w-5xl px-6 py-16 sm:py-24">
                            <h1 className="max-w-3xl text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
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
                        </div>
                    </section>

                    {/* Who this is for */}
                    <section className="border-t bg-muted/30">
                        <div className="mx-auto w-full max-w-5xl px-6 py-14">
                            <h2 className="text-sm font-medium text-muted-foreground">Who this is for</h2>
                            <div className="mt-6 grid gap-4 md:grid-cols-2">
                                {audiences.map(({ icon: Icon, title, description }) => (
                                    <Card
                                        key={title}
                                        className="border-l-4 border-l-primary transition-shadow hover:shadow-md"
                                    >
                                        <CardHeader>
                                            <div className="flex size-10 items-center justify-center rounded-lg bg-muted text-foreground">
                                                <Icon className="size-5" aria-hidden="true" />
                                            </div>
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
                            {steps.map(({ icon: Icon, label, accent }, index) => {
                                const nextAccent = steps[index + 1]?.accent;

                                return (
                                    <li key={label} className="relative flex flex-col gap-3">
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
                                        <p className="text-sm font-medium">{label}</p>
                                    </li>
                                );
                            })}
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
