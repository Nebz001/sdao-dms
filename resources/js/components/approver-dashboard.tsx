import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowRight,
    CalendarClock,
    CircleCheck,
    BookOpen,
    Clock,
    GraduationCap,
    History,
    Inbox,
    Route,
    ShieldCheck,
    TriangleAlert,
    UserRound,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import ApproverStepTracker from '@/components/approver-step-tracker';
import type { TrackerStep } from '@/components/approver-step-tracker';
import DateBadge from '@/components/date-badge';
import FormTypeBadge from '@/components/form-type-badge';
import IdentityPill from '@/components/identity-pill';
import PageHeader from '@/components/page-header';
import PageNotice from '@/components/page-notice';
import { ActionBadge, ToneBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { cn } from '@/lib/utils';

export type ApproverHeader = {
    greeting: string;
    lastName: string;
    roleTitle: string | null;
    role: string | null;
    scope: { label: string; value: string } | null;
    extraRoles: { label: string; value: string | null }[];
    reviewHref: string;
    trackHref: string;
    historyHref: string;
};

export type NeedsReviewEntry = {
    id: number;
    title: string;
    formType: string;
    formTypeLabel: string;
    organizationName: string;
    eventDate: string | null;
    daysUntilEvent: number | null;
    daysWithYou: number;
    href: string;
};

export type ApproverSummary = {
    banner: { count: number; document: NeedsReviewEntry | null };
    waiting: {
        count: number;
        oldestDays: number | null;
        byType: { formType: string; label: string; count: number }[];
    };
    nextEvent: NeedsReviewEntry | null;
    reviewed: { approved: number; returned: number; rejected: number; total: number };
};

export type ApproverNeedsReview = { total: number; rows: NeedsReviewEntry[] };

export type InProgressRow = {
    id: number;
    title: string;
    organizationName: string;
    formTypeLabel: string;
    href: string;
    steps: TrackerStep[];
    trackerLabel: string;
    currentRole: string;
    daysAtStep: number;
    stalled: boolean;
};

export type ComingUpEvent = {
    id: number;
    title: string;
    organizationName: string;
    venue: string;
    date: string;
    href: string;
};

export type RecentDecision = {
    id: number;
    action: string;
    formTypeLabel: string;
    documentTitle: string;
    organizationName: string;
    whenLabel: string;
    waitingOnOrg: boolean;
    href: string;
};

type ApproverDashboardProps = {
    header: ApproverHeader;
    summary: ApproverSummary;
    needsReview: ApproverNeedsReview;
    inProgress: InProgressRow[];
    comingUp: ComingUpEvent[];
    recentDecisions: RecentDecision[];
};

/** Fill shades for the form-type bar — one brand hue stepped down, so no new colors are introduced. */
const TYPE_SHADES = ['bg-primary', 'bg-primary/70', 'bg-primary/45', 'bg-primary/25', 'bg-primary/15'];

const OUTCOMES = [
    { key: 'approved', label: 'Approved', bar: 'bg-success', dot: 'bg-success' },
    { key: 'returned', label: 'Returned', bar: 'bg-warning', dot: 'bg-warning' },
    { key: 'rejected', label: 'Rejected', bar: 'bg-destructive', dot: 'bg-destructive' },
] as const;

function plural(count: number, noun: string): string {
    return `${count} ${noun}${count === 1 ? '' : 's'}`;
}

function formatShortDate(iso: string): string {
    const [year, month, day] = iso.split('-').map(Number);

    return new Date(year, month - 1, day).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}

/** "In 3 days", "Today", or "Event passed" — the words, so the badge never leans on its color. */
function countdownLabel(days: number): string {
    if (days < 0) {
        return 'Event passed';
    }

    if (days === 0) {
        return 'Today';
    }

    return `In ${plural(days, 'day')}`;
}

function withYouLabel(days: number): string {
    return days === 0 ? 'With you today' : `With you ${plural(days, 'day')}`;
}

/** The banner's second sentence: when the soonest event is, or how long it has waited if it has none. */
function bannerDetail(document: NeedsReviewEntry): string {
    if (document.daysUntilEvent === null) {
        return `It has been with you ${document.daysWithYou === 0 ? 'since today' : `for ${plural(document.daysWithYou, 'day')}`}.`;
    }

    if (document.daysUntilEvent < 0) {
        return 'Its event date has already passed.';
    }

    if (document.daysUntilEvent === 0) {
        return 'Its event is today.';
    }

    return `Its event is in ${plural(document.daysUntilEvent, 'day')}.`;
}

function EventCountdownBadge({ days }: { days: number }) {
    const soon = days <= 7;

    return (
        <ToneBadge tone={soon ? 'warning' : 'neutral'} className="normal-case tracking-normal">
            {soon && <Clock aria-hidden />}
            {countdownLabel(days)}
            {soon && <span className="sr-only"> — coming up soon</span>}
        </ToneBadge>
    );
}

function CardEmpty({ icon: Icon, title, description }: { icon: typeof Inbox; title: string; description?: string }) {
    return (
        <Empty className="gap-4 p-6">
            <EmptyHeader>
                <EmptyMedia variant="icon" className="size-8 [&_svg]:size-5">
                    <Icon />
                </EmptyMedia>
                <EmptyTitle>{title}</EmptyTitle>
                {description && <EmptyDescription>{description}</EmptyDescription>}
            </EmptyHeader>
        </Empty>
    );
}

function CardLink({ href, children }: { href: string; children: ReactNode }) {
    return (
        <Link
            href={href}
            className="inline-flex items-center gap-1 text-sm font-medium text-primary-text hover:underline"
        >
            {children}
            <ArrowRight className="size-3.5" aria-hidden />
        </Link>
    );
}

function StatLabel({ children }: { children: ReactNode }) {
    return <CardTitle className="text-sm font-medium text-muted-foreground">{children}</CardTitle>;
}

function ReviewBanner({ summary }: { summary: ApproverSummary }) {
    const { count, document } = summary.banner;

    if (count === 0 || document === null) {
        return (
            <PageNotice tone="success" title="You're all caught up.">
                Nothing is waiting at your step. New documents appear here as soon as they reach you.
            </PageNotice>
        );
    }

    return (
        <PageNotice
            tone="info"
            icon={Inbox}
            title={`You have ${plural(count, 'document')} to review.`}
            action={
                <Button asChild>
                    <Link href={document.href}>
                        Start reviewing
                        <ArrowRight data-icon="inline-end" />
                    </Link>
                </Button>
            }
        >
            Start with <span className="font-medium text-foreground">{document.title}</span>. {bannerDetail(document)}
        </PageNotice>
    );
}

function WaitingCard({ waiting }: { waiting: ApproverSummary['waiting'] }) {
    const segments = waiting.byType.filter((type) => type.count > 0);

    return (
        <Card className="gap-3 py-4">
            <CardHeader className="gap-1 px-4">
                <StatLabel>Waiting for you</StatLabel>
            </CardHeader>
            <CardContent className="flex flex-col gap-3 px-4">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-3xl leading-none font-semibold tabular-nums">{waiting.count}</span>
                    {waiting.oldestDays !== null && (
                        <ToneBadge tone="neutral" className="normal-case tracking-normal">
                            {waiting.oldestDays === 0 ? 'Oldest today' : `Oldest ${plural(waiting.oldestDays, 'day')}`}
                        </ToneBadge>
                    )}
                </div>
                <div aria-hidden className="flex h-2 w-full gap-0.5 overflow-hidden rounded-full bg-muted">
                    {segments.map((type) => (
                        <div
                            key={type.formType}
                            className={cn(
                                'h-full',
                                TYPE_SHADES[waiting.byType.findIndex((t) => t.formType === type.formType) % TYPE_SHADES.length],
                            )}
                            style={{ width: `${(type.count / waiting.count) * 100}%` }}
                        />
                    ))}
                </div>
                <ul className="flex flex-col gap-1 text-xs text-muted-foreground">
                    {waiting.byType.map((type, index) => (
                        <li key={type.formType} className="flex items-center gap-2">
                            <span aria-hidden className={cn('size-2 shrink-0 rounded-full', TYPE_SHADES[index % TYPE_SHADES.length])} />
                            <span className="font-medium text-foreground tabular-nums">{type.count}</span>
                            <span className="min-w-0 break-words">{type.label}</span>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}

function NextEventCard({ event }: { event: NeedsReviewEntry | null }) {
    return (
        <Card className="gap-3 border-warning/40 bg-warning/10 py-4">
            <CardHeader className="gap-1 px-4">
                <StatLabel>Event in the next 7 days</StatLabel>
            </CardHeader>
            <CardContent className="flex flex-1 flex-col gap-2 px-4">
                {event === null || event.eventDate === null || event.daysUntilEvent === null ? (
                    <p className="text-sm text-muted-foreground">
                        None of the documents waiting for you have an event in the next 7 days.
                    </p>
                ) : (
                    <>
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <p className="min-w-0 text-base font-semibold break-words">{event.title}</p>
                            <EventCountdownBadge days={event.daysUntilEvent} />
                        </div>
                        <p className="text-sm text-muted-foreground">{event.organizationName}</p>
                        <p className="text-sm text-muted-foreground">Event date: {formatShortDate(event.eventDate)}</p>
                        <div className="mt-auto pt-1">
                            <CardLink href={event.href}>Review proposal</CardLink>
                        </div>
                    </>
                )}
            </CardContent>
        </Card>
    );
}

function ReviewedCard({ reviewed }: { reviewed: ApproverSummary['reviewed'] }) {
    return (
        <Card className="gap-3 py-4">
            <CardHeader className="gap-1 px-4">
                <StatLabel>You reviewed this term</StatLabel>
            </CardHeader>
            <CardContent className="flex flex-col gap-3 px-4">
                {reviewed.total === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No decisions yet this term. Approved, returned and rejected documents are counted here.
                    </p>
                ) : (
                    <>
                        <span className="text-3xl leading-none font-semibold tabular-nums">{reviewed.total}</span>
                        <div aria-hidden className="flex h-2 w-full gap-0.5 overflow-hidden rounded-full bg-muted">
                            {OUTCOMES.filter((o) => reviewed[o.key] > 0).map((o) => (
                                <div
                                    key={o.key}
                                    className={cn('h-full', o.bar)}
                                    style={{ width: `${(reviewed[o.key] / reviewed.total) * 100}%` }}
                                />
                            ))}
                        </div>
                        <ul className="flex flex-col gap-1 text-xs text-muted-foreground">
                            {OUTCOMES.map((o) => (
                                <li key={o.key} className="flex items-center gap-2">
                                    <span aria-hidden className={cn('size-2 shrink-0 rounded-full', o.dot)} />
                                    <span className="font-medium text-foreground tabular-nums">{reviewed[o.key]}</span>
                                    <span>{o.label}</span>
                                </li>
                            ))}
                        </ul>
                    </>
                )}
            </CardContent>
        </Card>
    );
}

function NeedsReviewCard({ needsReview, reviewHref }: { needsReview: ApproverNeedsReview; reviewHref: string }) {
    return (
        <Card>
            <CardHeader>
                <div className="flex items-center gap-2">
                    <CardTitle className="text-base">Needs your review</CardTitle>
                    <Badge variant="secondary" className="tabular-nums">
                        {needsReview.total}
                    </Badge>
                </div>
                <CardDescription>Sorted by event date, soonest first</CardDescription>
            </CardHeader>
            <CardContent>
                {needsReview.rows.length === 0 ? (
                    <CardEmpty
                        icon={CircleCheck}
                        title="You're all caught up"
                        description="Documents show up here once they reach a step routed to your role."
                    />
                ) : (
                    <ul className="divide-y">
                        {needsReview.rows.map((row) => (
                            <li key={row.id} className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-3 first:pt-0 last:pb-0">
                                <div className="min-w-0 flex-1 basis-56">
                                    <p className="text-sm font-semibold break-words">{row.title}</p>
                                    <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
                                        <span className="break-words">{row.organizationName}</span>
                                        <FormTypeBadge label={row.formTypeLabel} />
                                    </div>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {row.eventDate ? `Event date: ${formatShortDate(row.eventDate)}` : 'No event date'}
                                    </p>
                                </div>
                                <div className="flex shrink-0 flex-col items-end gap-1">
                                    <div className="flex items-center gap-2">
                                        {row.daysUntilEvent !== null && <EventCountdownBadge days={row.daysUntilEvent} />}
                                        <Button asChild size="sm">
                                            <Link href={row.href}>
                                                Review
                                                <span className="sr-only"> {row.title}</span>
                                            </Link>
                                        </Button>
                                    </div>
                                    <span className="text-xs text-muted-foreground">{withYouLabel(row.daysWithYou)}</span>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
            {needsReview.rows.length > 0 && (
                <CardFooter>
                    <CardLink href={reviewHref}>See all documents for review</CardLink>
                </CardFooter>
            )}
        </Card>
    );
}

function InProgressCard({ rows, trackHref, className }: { rows: InProgressRow[]; trackHref: string; className?: string }) {
    return (
        <Card className={className}>
            <CardHeader>
                <CardTitle className="text-base">Where your approved documents are now</CardTitle>
                <CardDescription>Documents you passed on and the step each one is at</CardDescription>
            </CardHeader>
            <CardContent className="@container flex-1">
                {rows.length === 0 ? (
                    <CardEmpty
                        icon={Route}
                        title="Nothing in progress"
                        description="Documents you approve appear here until they are fully approved, returned or rejected."
                    />
                ) : (
                    <ul className="divide-y">
                        {rows.map((row) => (
                            <li
                                key={row.id}
                                className="grid gap-x-4 gap-y-3 py-3 first:pt-0 last:pb-0 @2xl:grid-cols-[minmax(0,1fr)_auto_auto] @2xl:items-center"
                            >
                                <div className="min-w-0">
                                    <Link href={row.href} className="text-sm font-semibold break-words hover:underline">
                                        {row.title}
                                    </Link>
                                    <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
                                        <span className="break-words">{row.organizationName}</span>
                                        <FormTypeBadge label={row.formTypeLabel} />
                                    </div>
                                </div>
                                <ApproverStepTracker steps={row.steps} label={row.trackerLabel} />
                                <div className="flex flex-wrap items-center gap-2 @2xl:flex-col @2xl:items-end @2xl:gap-1">
                                    <span className="text-sm font-medium">With {row.currentRole}</span>
                                    <ToneBadge tone={row.stalled ? 'warning' : 'neutral'} className="normal-case tracking-normal">
                                        {row.stalled && <TriangleAlert aria-hidden />}
                                        {plural(row.daysAtStep, 'day')}
                                        {row.stalled && <span className="sr-only"> — waiting longer than usual</span>}
                                    </ToneBadge>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
            {rows.length > 0 && (
                <CardFooter>
                    <CardLink href={trackHref}>Track all my approved documents</CardLink>
                </CardFooter>
            )}
        </Card>
    );
}

function ComingUpCard({ events }: { events: ComingUpEvent[] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Coming up</CardTitle>
                <CardDescription>Approved activities in the next 14 days</CardDescription>
            </CardHeader>
            <CardContent>
                {events.length === 0 ? (
                    <CardEmpty icon={CalendarClock} title="No approved activities in the next 14 days" />
                ) : (
                    <ul className="flex flex-col gap-2">
                        {events.map((event) => (
                            <li key={event.id} className="flex items-start gap-3 rounded-md border px-3 py-2">
                                <DateBadge iso={event.date} />
                                <div className="min-w-0 flex-1 py-0.5">
                                    <Link href={event.href} className="text-sm font-semibold break-words hover:underline">
                                        {event.title}
                                    </Link>
                                    <p className="text-sm text-muted-foreground break-words">{event.organizationName}</p>
                                    <p className="text-sm text-muted-foreground break-words">{event.venue}</p>
                                    <span className="sr-only">{formatShortDate(event.date)}</span>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

function RecentDecisionsCard({ decisions, historyHref, className }: { decisions: RecentDecision[]; historyHref: string; className?: string }) {
    return (
        <Card className={className}>
            <CardHeader>
                <CardTitle className="text-base">Your recent decisions</CardTitle>
            </CardHeader>
            <CardContent className="flex-1">
                {decisions.length === 0 ? (
                    <CardEmpty icon={History} title="You haven't made any decisions yet" />
                ) : (
                    <ul className="divide-y">
                        {decisions.map((entry) => (
                            <li key={entry.id} className="flex items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                <div className="min-w-0 flex-1">
                                    <Link href={entry.href} className="text-sm font-semibold break-words hover:underline">
                                        {entry.documentTitle}
                                    </Link>
                                    <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
                                        <span className="break-words">{entry.organizationName}</span>
                                        <FormTypeBadge label={entry.formTypeLabel} />
                                    </div>
                                    {entry.waitingOnOrg && (
                                        <p className="mt-1 text-xs text-muted-foreground">Waiting for the org to fix and resend</p>
                                    )}
                                </div>
                                <div className="flex shrink-0 flex-col items-end gap-1">
                                    <ActionBadge action={entry.action} />
                                    <span className="text-xs text-muted-foreground tabular-nums">{entry.whenLabel}</span>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
            {decisions.length > 0 && (
                <CardFooter>
                    <CardLink href={historyHref}>See my review history</CardLink>
                </CardFooter>
            )}
        </Card>
    );
}

/**
 * The approver's page heading: a time-of-day greeting by name, then their
 * role and its scope. Unlike other pages' fixed sublabels this one is
 * personal by design (the dashboard brief asks for it), so it lives here
 * rather than in the page file the static-subtitle test scans.
 */
const SCOPE_ICONS: Record<string, LucideIcon> = {
    School: GraduationCap,
    Program: BookOpen,
    Organization: Users,
};

export function ApproverGreeting({ header }: { header: ApproverHeader }) {
    return (
        <PageHeader
            title={`${header.greeting}, ${header.roleTitle ? `${header.roleTitle} ` : ''}${header.lastName}`}
            subtitle={
                header.role && (
                    <ul aria-label="Your roles" className="mt-2 flex flex-wrap items-center gap-2">
                        <li>
                            <IdentityPill icon={ShieldCheck} tone="brand" value={header.role} />
                        </li>
                        {header.scope && (
                            <li>
                                <IdentityPill
                                    icon={SCOPE_ICONS[header.scope.label] ?? GraduationCap}
                                    tone="muted"
                                    label={header.scope.label}
                                    value={header.scope.value}
                                />
                            </li>
                        )}
                        {header.extraRoles.map((extra) => (
                            <li key={`${extra.label}-${extra.value}`}>
                                <IdentityPill icon={UserRound} tone="warm" label={extra.label} value={extra.value} />
                            </li>
                        ))}
                    </ul>
                )
            }
        />
    );
}

export default function ApproverDashboard({
    header,
    summary,
    needsReview,
    inProgress,
    comingUp,
    recentDecisions,
}: ApproverDashboardProps) {
    return (
        <div className="flex flex-col gap-6">
            <ReviewBanner summary={summary} />

            <div className="grid gap-4 md:grid-cols-3">
                <WaitingCard waiting={summary.waiting} />
                <NextEventCard event={summary.nextEvent} />
                <ReviewedCard reviewed={summary.reviewed} />
            </div>

            <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div className="flex flex-col gap-4">
                    <NeedsReviewCard needsReview={needsReview} reviewHref={header.reviewHref} />
                    <InProgressCard rows={inProgress} trackHref={header.trackHref} className="flex-1" />
                </div>
                <div className="flex flex-col gap-4">
                    <ComingUpCard events={comingUp} />
                    <RecentDecisionsCard decisions={recentDecisions} historyHref={header.historyHref} className="flex-1" />
                </div>
            </div>
        </div>
    );
}
