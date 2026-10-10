import { Link, usePage } from '@inertiajs/react';
import {
    ChevronRight,
    CircleCheck,
    Clock,
    ExternalLink,
    GraduationCap,
    LayoutGrid,
    Search,
    TriangleAlert,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import IconTile from '@/components/icon-tile';
import { ToneBadge } from '@/components/status-badge';
import type {
    NeedsActionData,
    StudentDashboardMeta,
    StudentKpis,
    TrackerData,
} from '@/components/student-dashboard';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { BRAND_TINT_OUTLINE } from '@/lib/brand-tint';
import { buildNavSections } from '@/lib/nav-config';
import { labelFor, toneFor } from '@/lib/status-tones';
import {
    bannerState,
    buildHomeCards,
    filterHomeCards,
    greetingFor,
    pluralize,
} from '@/lib/student-home';
import type { HomeCard } from '@/lib/student-home';
import { cn } from '@/lib/utils';

const CARD_FACE =
    'group relative flex h-full w-full flex-col gap-4 rounded-xl border border-card-border bg-card p-6 text-left text-card-foreground shadow-sm transition-colors hover:border-foreground/30 focus-visible:focus-ring';

/** The card's contents. Spans only, so it is valid inside the link. */
function CardFace({ card }: { card: HomeCard }) {
    const hasOptions = card.items !== null;

    return (
        <>
            <IconTile icon={card.icon} />
            {card.badge !== null && (
                <span
                    data-slot="home-card-badge"
                    className="absolute top-5 right-5 inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-primary px-1.5 text-xs font-semibold text-primary-foreground tabular-nums"
                >
                    {card.badge}
                    <span className="sr-only"> documents</span>
                </span>
            )}
            <span className="flex flex-col gap-1">
                <span className="text-lg font-semibold">{card.title}</span>
                <span className="text-sm text-muted-foreground">
                    {card.description}
                </span>
            </span>
            <span className="mt-auto flex items-center justify-between gap-2 border-t pt-4 text-sm">
                <span className="flex items-center gap-1.5 text-muted-foreground">
                    {hasOptions ? (
                        <LayoutGrid aria-hidden className="size-4" />
                    ) : (
                        <ExternalLink aria-hidden className="size-4" />
                    )}
                    {hasOptions
                        ? pluralize(card.items?.length ?? 0, 'option')
                        : 'Opens page'}
                </span>
                <span className="flex items-center gap-1 font-medium text-primary-text">
                    {hasOptions ? 'Open' : 'Go'}
                    <ChevronRight aria-hidden className="size-4" />
                </span>
            </span>
        </>
    );
}

function HomeCardItem({ card }: { card: HomeCard }) {
    return (
        <Link href={card.href} prefetch className={CARD_FACE}>
            <CardFace card={card} />
        </Link>
    );
}

function IdentityPills({ meta }: { meta: StudentDashboardMeta }) {
    const { auth } = usePage().props;
    const organization = auth?.organization ?? null;
    const status = meta.organizationStatus;
    const statusTone = toneFor('organization', status);
    const StatusIcon =
        statusTone === 'success'
            ? CircleCheck
            : statusTone === 'warning' || statusTone === 'destructive'
              ? TriangleAlert
              : Clock;
    const pill =
        'h-auto min-h-7 max-w-full gap-1.5 px-3 py-1 text-left text-sm tracking-normal whitespace-normal normal-case';

    return (
        <ul
            className="flex flex-wrap items-center gap-2"
            aria-label="Your role"
        >
            <li>
                <ToneBadge tone="neutral" className={cn(pill, BRAND_TINT_OUTLINE)}>
                    <Users aria-hidden />
                    <span className="font-semibold">
                        {meta.officerPosition}
                    </span>
                    <span aria-hidden className="h-3.5 w-px bg-brand-soft-border" />
                    <span>{organization?.name ?? meta.organizationName}</span>
                </ToneBadge>
            </li>
            {organization?.school && (
                <li>
                    <ToneBadge tone="neutral" className={pill}>
                        <GraduationCap aria-hidden />
                        {organization.school.name}
                    </ToneBadge>
                </li>
            )}
            <li>
                <ToneBadge tone={statusTone} className={pill}>
                    <StatusIcon aria-hidden />
                    {status === 'active'
                        ? 'Active organization'
                        : labelFor('organization', status)}
                </ToneBadge>
            </li>
        </ul>
    );
}

export function HomeStatusBannerSkeleton() {
    return (
        <Card aria-hidden>
            <CardContent className="flex items-center gap-4">
                <Skeleton className="size-10 rounded-lg" />
                <div className="flex flex-1 flex-col gap-2">
                    <Skeleton className="h-4 w-40" />
                    <Skeleton className="h-4 w-72 max-w-full" />
                </div>
            </CardContent>
        </Card>
    );
}

/**
 * One wide card: caught up, or a warning with the count when something needs
 * the student (returned documents, unfinished drafts). Fed by the same
 * deferred data the old Needs Your Action and In Progress sections used.
 */
export function HomeStatusBanner({
    needsAction,
    tracker,
    kpis,
    trackHref,
}: {
    needsAction: NeedsActionData;
    tracker: TrackerData;
    kpis: StudentKpis;
    trackHref: string;
}) {
    const returnedCount = Math.min(kpis.needsRevision.count, needsAction.total);
    const state = bannerState({
        needsActionTotal: needsAction.total,
        returnedCount,
        draftCount: needsAction.total - returnedCount,
        inChainTotal: tracker.total,
    });
    const warning = state.kind === 'needs-action';
    const Icon = warning ? TriangleAlert : CircleCheck;

    return (
        <Card
            data-state={state.kind}
            role="status"
            className={cn(warning && 'border-warning/50 bg-warning/5')}
        >
            <CardContent className="flex flex-wrap items-center justify-between gap-4">
                <div className="flex items-center gap-4">
                    <span
                        aria-hidden
                        className={cn(
                            'flex size-10 shrink-0 items-center justify-center rounded-lg',
                            warning
                                ? 'bg-warning/15 text-warning-foreground'
                                : 'bg-success/10 text-success-foreground',
                        )}
                    >
                        <Icon className="size-5" />
                    </span>
                    <div>
                        <p className="font-semibold">{state.title}</p>
                        <p className="text-sm text-muted-foreground">
                            {state.description}
                        </p>
                    </div>
                </div>
                <Button
                    asChild
                    variant="link"
                    className="px-0 text-primary-text"
                >
                    <Link href={trackHref} prefetch>
                        Track my documents
                        <ChevronRight data-icon="inline-end" />
                    </Link>
                </Button>
            </CardContent>
        </Card>
    );
}

/**
 * The student home page body: greeting, identity pills, the status banner
 * (passed in, because it waits on deferred data) and the searchable card grid.
 */
export default function StudentHome({
    meta,
    banner,
}: {
    meta: StudentDashboardMeta | null;
    banner: ReactNode;
}) {
    const { auth, navCounts } = usePage().props;
    const [query, setQuery] = useState('');
    const user = auth?.user;
    const firstName = user?.first_name ?? user?.name.split(' ')[0] ?? '';
    const greeting = useMemo(() => greetingFor(), []);

    const cards = useMemo(
        () => buildHomeCards(buildNavSections(auth, navCounts)),
        [auth, navCounts],
    );
    const visibleCards = useMemo(
        () => filterHomeCards(cards, query),
        [cards, query],
    );

    return (
        <div className="flex flex-col gap-8">
            <div className="flex flex-col gap-3">
                <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">
                    {greeting}, {firstName}
                </h1>
                {meta && <IdentityPills meta={meta} />}
            </div>

            {banner}

            <section
                aria-labelledby="home-actions-heading"
                className="flex flex-col gap-4"
            >
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2
                            id="home-actions-heading"
                            className="text-xl font-bold tracking-tight"
                        >
                            What do you want to do?
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Pick a box to open it
                        </p>
                    </div>
                    {cards.length > 0 && (
                        <div
                            role="search"
                            className="relative w-full sm:max-w-xs"
                        >
                            <Search
                                aria-hidden
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                type="search"
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Search forms and pages"
                                aria-label="Search forms and pages"
                                className="h-10 pl-9"
                            />
                        </div>
                    )}
                </div>

                <p className="sr-only" aria-live="polite">
                    {query.trim() !== '' &&
                        `${pluralize(visibleCards.length, 'result')} for ${query.trim()}`}
                </p>

                {visibleCards.length > 0 ? (
                    <ul className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {visibleCards.map((card) => (
                            <li key={card.key}>
                                <HomeCardItem card={card} />
                            </li>
                        ))}
                    </ul>
                ) : (
                    <Empty className="border">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Search />
                            </EmptyMedia>
                            <EmptyTitle>Nothing matches your search</EmptyTitle>
                            <EmptyDescription>
                                {query.trim() !== ''
                                    ? `No forms or pages match “${query.trim()}”. Try a different word.`
                                    : 'There is nothing you can open yet.'}
                            </EmptyDescription>
                        </EmptyHeader>
                        {query.trim() !== '' && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setQuery('')}
                            >
                                Clear search
                            </Button>
                        )}
                    </Empty>
                )}
            </section>
        </div>
    );
}
